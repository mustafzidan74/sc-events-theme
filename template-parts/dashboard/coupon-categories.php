<?php
/**
 * Coupon categories — cards with counts, edited in a dialog.
 *
 * Categories only organise coupons in the dashboard; registration ignores them.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

global $load_wd_overview, $load_wd_form;
$load_wd_overview = true;
$load_wd_form = true;

$dashboard_url = home_url('/event-manager-dashboard/');
$js = function ($value) {
    return wp_json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
};

get_template_part('template-parts/dashboard/components/dashboard', 'header');
get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');
?>

<div id="main-content">
<div class="container-fluid">

    <div class="w-page-head">
        <div>
            <h1><?php echo esc_html(sc_t('dashboard_pages.coupon_categories', 'Coupon categories')); ?><span class="w-page-head__count" id="cat-count"></span></h1>
            <p class="w-page-head__sub"><?php echo esc_html(sc_t('dashboard_pages.coupon_categories_sub', 'Group codes by who they’re for — attendees, invitations, sponsors. Categories only organise the list; they don’t change how a code works.')); ?></p>
        </div>
        <div class="w-page-head__actions">
            <a class="btn btn-secondary" href="<?php echo esc_url($dashboard_url . 'coupons'); ?>"><?php echo esc_html(sc_t('dashboard_pages.coupons_management', 'Coupons')); ?></a>
            <button type="button" class="btn btn-primary" id="add-category"><i class="fa fa-plus" aria-hidden="true"></i> <?php echo esc_html(sc_t('dashboard_pages.add_category', 'Add category')); ?></button>
        </div>
    </div>

    <div id="cat-body" class="w-catgrid" aria-live="polite"><div class="w-state"><span class="w-spinner" aria-hidden="true"></span></div></div>

</div>
</div>

<div class="modal fade" id="catModal" tabindex="-1" role="dialog" aria-labelledby="cat-title">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="cat-form" novalidate autocomplete="off">
                <input type="hidden" name="id" value="0">
                <div class="modal-header">
                    <h5 class="modal-title" id="cat-title"></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="<?php echo esc_attr(sc_t('dashboard_pages.close', 'Close')); ?>"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="w-errors" data-w-errors hidden></div>
                    <div class="w-fields">
                        <div class="w-field">
                            <label for="cat-name"><?php echo esc_html(sc_t('dashboard_pages.name', 'Name')); ?><span class="w-req" aria-hidden="true">*</span></label>
                            <input type="text" class="form-control" id="cat-name" name="name" maxlength="100" required>
                        </div>
                        <div class="w-fields w-fields--2">
                            <div class="w-field">
                                <label for="cat-color"><?php echo esc_html(sc_t('dashboard_pages.colour', 'Colour')); ?></label>
                                <input type="color" class="form-control w-color" id="cat-color" name="color" value="#7c1314">
                            </div>
                            <div class="w-field">
                                <label for="cat-order"><?php echo esc_html(sc_t('dashboard_pages.order', 'Order')); ?></label>
                                <input type="number" class="form-control" id="cat-order" name="sort_order" value="0" step="1" inputmode="numeric">
                                <p class="w-field__help"><?php echo esc_html(sc_t('dashboard_pages.order_help', 'Lower numbers come first in menus.')); ?></p>
                            </div>
                        </div>
                        <div class="w-field">
                            <label for="cat-desc"><?php echo esc_html(sc_t('dashboard_pages.description', 'Description')); ?></label>
                            <textarea class="form-control" id="cat-desc" name="description" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-link text-danger mr-auto" id="cat-delete" hidden><?php echo esc_html(sc_t('dashboard_pages.delete', 'Delete')); ?></button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo esc_html(sc_t('dashboard_pages.cancel', 'Cancel')); ?></button>
                    <button type="submit" class="btn btn-primary" data-w-save><?php echo esc_html(sc_t('dashboard_pages.save', 'Save')); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
jQuery(function ($) {
    'use strict';

    var dashboardUrl = <?php echo $js($dashboard_url); ?>;
    var L = <?php echo $js(array(
        'add'          => sc_t('dashboard_pages.add_category', 'Add category'),
        'edit'         => sc_t('dashboard_pages.edit_category', 'Edit category'),
        'coupons'      => sc_t('dashboard_pages.n_coupons', '%s coupons'),
        'used'         => sc_t('dashboard_pages.n_used', '%s used'),
        'protected'    => sc_t('dashboard_pages.default_category', 'Default'),
        'protectedHelp'=> sc_t('dashboard_pages.default_category_help', 'Coupons without a category count here. It can’t be deleted.'),
        'view'         => sc_t('dashboard_pages.view_coupons', 'View coupons'),
        'editBtn'      => sc_t('dashboard_pages.edit', 'Edit'),
        'empty'        => sc_t('dashboard_pages.no_categories', 'No categories yet.'),
        'errName'      => sc_t('dashboard_pages.err_name', 'Enter a name.'),
        'confirmDelete'=> sc_t('dashboard_pages.confirm_delete_category', 'Delete “%1$s”? Its %2$s coupons move to General and keep working.'),
        'loadFailed'   => sc_t('dashboard_pages.load_failed', 'This could not be loaded.'),
        'retry'        => sc_t('dashboard_pages.retry', 'Try again'),
        'failed'       => sc_t('errors.something_wrong', 'Something went wrong. Please try again.'),
        'saving'       => sc_t('dashboard_pages.saving', 'Saving…'),
        'fixErrors'    => sc_t('dashboard_pages.fix_n', 'Fix %d to save'),
        'errorsTitle'  => sc_t('dashboard_pages.errors_title', '%d fields need attention before saving.'),
        'errorTitleOne'=> sc_t('dashboard_pages.error_title_one', 'One field needs attention before saving.'),
        'leave'        => sc_t('dashboard_pages.unsaved_leave', 'You have unsaved changes.'),
    )); ?>;
    var esc = function (v) { return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]; }); };
    var num = function (n) { return Number(n || 0).toLocaleString('en-US'); };
    var post = function (data) { return $.ajax({ url: scDashboard.ajaxurl, type: 'POST', data: $.extend({ nonce: scDashboard.nonce }, data) }); };
    var cats = [];
    var $body = $('#cat-body'), $modal = $('#catModal'), formEl = document.getElementById('cat-form');

    function load() {
        return post({ action: 'sc_get_coupon_categories' }).done(function (res) {
            if (!res.success) { return failed(); }
            cats = res.data.categories || [];
            $('#cat-count').text(num(cats.length));
            if (!cats.length) { $body.html('<div class="w-state"><p class="w-state__text">' + esc(L.empty) + '</p></div>'); return; }
            $body.html(cats.map(function (c) {
                var isDefault = +c.is_protected === 1 || +c.id === 1;
                return '<article class="w-cat">' +
                    '<span class="w-cat__bar" style="background:' + esc(c.color) + '" aria-hidden="true"></span>' +
                    '<div class="w-cat__body">' +
                        '<h2 class="w-cat__name">' + esc(c.name) + (isDefault ? ' <span class="w-tag" title="' + esc(L.protectedHelp) + '">' + esc(L.protected) + '</span>' : '') + '</h2>' +
                        (c.description ? '<p class="w-sub">' + esc(c.description) + '</p>' : '') +
                        '<p class="w-cat__stats"><strong>' + esc(L.coupons.replace('%s', num(c.coupon_count))) + '</strong> · ' + esc(L.used.replace('%s', num(c.used_count))) + '</p>' +
                    '</div>' +
                    '<div class="w-cat__actions">' +
                        '<a class="btn btn-sm btn-secondary" href="' + esc(dashboardUrl + 'coupons?category_id=' + c.id) + '">' + esc(L.view) + '</a>' +
                        '<button type="button" class="btn btn-sm btn-secondary" data-edit="' + c.id + '">' + esc(L.editBtn) + '</button>' +
                    '</div>' +
                '</article>';
            }).join(''));
        }).fail(failed);
    }
    function failed() {
        $body.html('<div class="w-state"><p class="w-state__text">' + esc(L.loadFailed) + '</p><button type="button" class="btn btn-secondary" id="cat-retry">' + esc(L.retry) + '</button></div>');
    }
    $body.on('click', '#cat-retry', load);

    var form = WDForm.create({
        form: formEl,
        i18n: { saving: L.saving, fixErrors: L.fixErrors, errorsTitle: L.errorsTitle, errorTitleOne: L.errorTitleOne, failed: L.failed, leave: L.leave },
        validate: function (v) { return $.trim(v.name) ? [] : [{ field: 'name', message: L.errName }]; },
        submit: function (fd) {
            fd.append('action', 'sc_save_coupon_category');
            fd.append('nonce', scDashboard.nonce);
            return fetch(scDashboard.ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); });
        },
        onSuccess: function (data, api) {
            api.markClean();
            $modal.modal('hide');
            showSuccess(data.message);
            load();
        }
    });

    function open(cat) {
        formEl.reset();
        formEl.elements.id.value = cat ? cat.id : 0;
        formEl.elements.name.value = cat ? cat.name : '';
        formEl.elements.color.value = cat && /^#[0-9a-f]{6}$/i.test(cat.color) ? cat.color : '#7c1314';
        formEl.elements.sort_order.value = cat ? cat.sort_order : cats.length;
        formEl.elements.description.value = cat ? (cat.description || '') : '';
        $('#cat-title').text(cat ? L.edit : L.add);
        $('#cat-delete').prop('hidden', !cat || +cat.is_protected === 1 || +cat.id === 1);
        form.markClean();
        $modal.modal('show');
    }
    $modal.on('shown.bs.modal', function () { $('#cat-name').trigger('focus'); });
    $('#add-category').on('click', function () { open(null); });
    $body.on('click', '[data-edit]', function () {
        var id = +this.getAttribute('data-edit');
        open(cats.filter(function (c) { return +c.id === id; })[0]);
    });
    $('#cat-delete').on('click', function () {
        var id = +formEl.elements.id.value;
        var cat = cats.filter(function (c) { return +c.id === id; })[0];
        showDeleteConfirm(L.confirmDelete.replace('%1$s', cat.name).replace('%2$s', num(cat.coupon_count))).then(function (r) {
            if (!r.isConfirmed) { return; }
            post({ action: 'sc_delete_coupon_category', id: id }).done(function (res) {
                if (res.success) { form.markClean(); $modal.modal('hide'); showSuccess(res.data.message); load(); }
                else { showError(res.data && res.data.message || L.failed); }
            }).fail(function () { showError(L.failed); });
        });
    });

    load();
});
</script>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
