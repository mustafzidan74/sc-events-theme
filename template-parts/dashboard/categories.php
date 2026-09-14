<?php
/**
 * Event categories — cards with how many events use each, edited in a dialog.
 *
 * Categories are sc_event_category terms; events store them by term id in
 * sc_event_categories and pick them in the event form.
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
            <h1><?php echo esc_html(sc_t('dashboard_pages.event_categories', 'Event categories')); ?><span class="w-page-head__count" id="cat-count"></span></h1>
            <p class="w-page-head__sub"><?php echo esc_html(sc_t('dashboard_pages.event_categories_sub', 'Group events by field — endodontics, orthodontics, students. Pick them in the event form’s Basics section.')); ?></p>
        </div>
        <div class="w-page-head__actions">
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
                <input type="hidden" name="category_id" value="0">
                <div class="modal-header">
                    <h5 class="modal-title" id="cat-title"></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="<?php echo esc_attr(sc_t('dashboard_pages.close', 'Close')); ?>"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="w-errors" data-w-errors hidden></div>
                    <div class="w-fields">
                        <div class="w-fields w-fields--wide">
                            <div class="w-field">
                                <label for="cat-name"><?php echo esc_html(sc_t('dashboard_pages.name', 'Name')); ?><span class="w-req" aria-hidden="true">*</span></label>
                                <input type="text" class="form-control" id="cat-name" name="category_name" maxlength="200" required>
                            </div>
                            <div class="w-field">
                                <label for="cat-color"><?php echo esc_html(sc_t('dashboard_pages.colour', 'Colour')); ?></label>
                                <input type="color" class="form-control w-color" id="cat-color" name="category_color" value="#7c1314">
                            </div>
                        </div>
                        <div class="w-field">
                            <label for="cat-slug"><?php echo esc_html(sc_t('dashboard_pages.url', 'Web address')); ?></label>
                            <input type="text" class="form-control w-ltr" id="cat-slug" name="category_slug" spellcheck="false">
                            <p class="w-field__help"><?php echo esc_html(sc_t('dashboard_pages.slug_auto_help', 'Leave empty to make one from the name.')); ?></p>
                        </div>
                        <div class="w-field">
                            <label for="cat-desc"><?php echo esc_html(sc_t('dashboard_pages.description', 'Description')); ?></label>
                            <textarea class="form-control" id="cat-desc" name="category_description" rows="3"></textarea>
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

    var termBase = <?php echo $js(home_url('/')); ?>;
    var L = <?php echo $js(array(
        'add'          => sc_t('dashboard_pages.add_category', 'Add category'),
        'edit'         => sc_t('dashboard_pages.edit_category', 'Edit category'),
        'events'       => sc_t('dashboard_pages.n_events', '%s events'),
        'oneEvent'     => sc_t('dashboard_pages.one_event', '1 event'),
        'editBtn'      => sc_t('dashboard_pages.edit', 'Edit'),
        'empty'        => sc_t('dashboard_pages.no_event_categories', 'No categories yet. Add one, then pick it in an event’s Basics section.'),
        'errName'      => sc_t('dashboard_pages.err_name', 'Enter a name.'),
        'confirmDelete'=> sc_t('dashboard_pages.confirm_delete_event_category', 'Delete “%1$s”? It’s removed from %2$s events. The events themselves stay.'),
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
        return post({ action: 'sc_get_categories_paginated', per_page: 500 }).done(function (res) {
            if (!res.success) { return failed(); }
            cats = res.data.categories || [];
            $('#cat-count').text(num(res.data.total));
            if (!cats.length) { $body.html('<div class="w-state"><p class="w-state__text">' + esc(L.empty) + '</p></div>'); return; }
            $body.html(cats.map(function (c) {
                return '<article class="w-cat">' +
                    '<span class="w-cat__bar" style="background:' + esc(c.color || '#7c1314') + '" aria-hidden="true"></span>' +
                    '<div class="w-cat__body">' +
                        '<h2 class="w-cat__name">' + esc(c.name) + '</h2>' +
                        (c.description ? '<p class="w-sub">' + esc(c.description) + '</p>' : '') +
                        '<p class="w-cat__stats"><strong>' + esc(c.count === 1 ? L.oneEvent : L.events.replace('%s', num(c.count))) + '</strong> · <span class="w-ltr">/' + esc(c.slug) + '</span></p>' +
                    '</div>' +
                    '<div class="w-cat__actions"><button type="button" class="btn btn-sm btn-secondary" data-edit="' + c.term_id + '">' + esc(L.editBtn) + '</button></div>' +
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
        validate: function (v) { return $.trim(v.category_name) ? [] : [{ field: 'category_name', message: L.errName }]; },
        submit: function (fd) {
            fd.append('action', 'sc_save_category');
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
        formEl.elements.category_id.value = cat ? cat.term_id : 0;
        formEl.elements.category_name.value = cat ? cat.name : '';
        formEl.elements.category_slug.value = cat ? cat.slug : '';
        formEl.elements.category_color.value = cat && /^#[0-9a-f]{6}$/i.test(cat.color || '') ? cat.color : '#7c1314';
        formEl.elements.category_description.value = cat ? (cat.description || '') : '';
        $('#cat-title').text(cat ? L.edit : L.add);
        $('#cat-delete').prop('hidden', !cat);
        form.markClean();
        $modal.modal('show');
    }
    $modal.on('shown.bs.modal', function () { $('#cat-name').trigger('focus'); });
    $('#add-category').on('click', function () { open(null); });
    $body.on('click', '[data-edit]', function () {
        var id = +this.getAttribute('data-edit');
        open(cats.filter(function (c) { return +c.term_id === id; })[0]);
    });
    $('#cat-delete').on('click', function () {
        var id = +formEl.elements.category_id.value;
        var cat = cats.filter(function (c) { return +c.term_id === id; })[0];
        showDeleteConfirm(L.confirmDelete.replace('%1$s', cat.name).replace('%2$s', num(cat.count))).then(function (r) {
            if (!r.isConfirmed) { return; }
            post({ action: 'sc_delete_category', category_id: id }).done(function (res) {
                if (res.success) { form.markClean(); $modal.modal('hide'); showSuccess(res.data.message); load(); }
                else { showError(res.data && res.data.message || L.failed); }
            }).fail(function () { showError(L.failed); });
        });
    });

    load();
});
</script>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
