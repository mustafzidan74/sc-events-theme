<?php
/**
 * Halls — the rooms programme items take place in, edited in a dialog.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

global $load_wd_overview;
$load_wd_overview = true;
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
            <h1><?php echo esc_html(sc_t('nav.halls', 'Halls')); ?><span class="w-page-head__count" id="halls-count"></span></h1>
            <p class="w-page-head__sub"><?php echo esc_html(sc_t('dashboard_pages.halls_subtitle', 'Rooms used in the programme. Their names and capacity appear on the event page.')); ?></p>
        </div>
        <div class="w-page-head__actions">
            <a class="btn btn-secondary" href="<?php echo esc_url($dashboard_url . 'schedules'); ?>"><i class="fa fa-clock-o" aria-hidden="true"></i> <?php echo esc_html(sc_t('nav.schedules', 'Schedules')); ?></a>
            <button type="button" class="btn btn-primary" id="hall-add"><i class="fa fa-plus" aria-hidden="true"></i> <?php echo esc_html(sc_t('dashboard_pages.add_hall', 'Add hall')); ?></button>
        </div>
    </div>

    <div id="halls-body" class="w-hallgrid" aria-live="polite"></div>
</div>
</div>

<div class="modal fade" id="hallModal" tabindex="-1" role="dialog" aria-labelledby="hall-title">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="hall-form" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="hall-title"><?php echo esc_html(sc_t('dashboard_pages.add_hall', 'Add hall')); ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="<?php echo esc_attr(sc_t('dashboard_pages.close', 'Close')); ?>"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="hall_id" value="">
                    <input type="hidden" name="image" value="">
                    <div class="w-fields">
                        <div class="w-field">
                            <label for="h-name"><?php echo esc_html(sc_t('dashboard_pages.name', 'Name')); ?><span class="w-req" aria-hidden="true">*</span></label>
                            <input type="text" class="form-control" id="h-name" name="name" maxlength="255" required>
                        </div>
                        <div class="w-fields w-fields--2">
                            <div class="w-field">
                                <label for="h-capacity"><?php echo esc_html(sc_t('dashboard_pages.capacity', 'Capacity')); ?></label>
                                <input type="number" class="form-control" id="h-capacity" name="capacity" min="0" step="1" inputmode="numeric">
                                <p class="w-field__help"><?php echo esc_html(sc_t('dashboard_pages.seats_in_room', 'Seats in the room; shown next to its name.')); ?></p>
                            </div>
                            <div class="w-field">
                                <label for="h-order"><?php echo esc_html(sc_t('dashboard_pages.order', 'Order')); ?></label>
                                <input type="number" class="form-control" id="h-order" name="sort_order" step="1" value="0" inputmode="numeric">
                            </div>
                        </div>
                        <div class="w-field">
                            <label for="h-location"><?php echo esc_html(sc_t('dashboard_pages.location', 'Location')); ?></label>
                            <input type="text" class="form-control" id="h-location" name="location" maxlength="255" placeholder="<?php echo esc_attr(sc_t('dashboard_pages.location_placeholder', 'Building, floor…')); ?>">
                        </div>
                        <div class="w-field">
                            <label for="h-description"><?php echo esc_html(sc_t('dashboard_pages.description', 'Description')); ?></label>
                            <textarea class="form-control" id="h-description" name="description" rows="2"></textarea>
                        </div>
                        <div class="w-field">
                            <span class="w-field__label"><?php echo esc_html(sc_t('dashboard_pages.photo', 'Photo')); ?></span>
                            <div class="w-hall-photo">
                                <img id="h-photo" src="" alt="" hidden>
                                <button type="button" class="btn btn-sm btn-secondary" id="h-photo-choose"><?php echo esc_html(sc_t('dashboard_pages.replace', 'Choose')); ?></button>
                                <button type="button" class="btn btn-sm btn-secondary" id="h-photo-remove" hidden><?php echo esc_html(sc_t('dashboard_pages.remove', 'Remove')); ?></button>
                            </div>
                        </div>
                        <input type="hidden" name="is_active" value="0">
                        <label class="w-switch">
                            <input type="checkbox" name="is_active" value="1" checked>
                            <span class="w-switch__track" aria-hidden="true"></span>
                            <span class="w-switch__text"><strong><?php echo esc_html(sc_t('dashboard_pages.in_use', 'In use')); ?></strong><span><?php echo esc_html(sc_t('dashboard_pages.hall_active_help', 'Inactive halls are hidden from the event page and moved to the end of the hall list.')); ?></span></span>
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-link text-danger mr-auto" id="hall-delete" hidden><?php echo esc_html(sc_t('dashboard_pages.delete', 'Delete')); ?></button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo esc_html(sc_t('dashboard_pages.cancel', 'Cancel')); ?></button>
                    <button type="submit" class="btn btn-primary" id="hall-save"><?php echo esc_html(sc_t('dashboard_pages.save', 'Save')); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
jQuery(function ($) {
    'use strict';

    var L = <?php echo $js(array(
        'add'       => sc_t('dashboard_pages.add_hall', 'Add hall'),
        'edit'      => sc_t('dashboard_pages.edit_hall', 'Edit hall'),
        'empty'     => sc_t('dashboard_pages.no_halls', 'No halls yet. Add the rooms your programme uses.'),
        'seats'     => sc_t('dashboard_pages.n_seats', '%s seats'),
        'items'     => sc_t('dashboard_pages.n_programme_items', '%d programme items'),
        'unused'    => sc_t('dashboard_pages.not_used_yet', 'Not used yet'),
        'inactive'  => sc_t('dashboard_pages.inactive', 'inactive'),
        'errName'   => sc_t('dashboard_pages.err_hall_name', 'Give the hall a name.'),
        'saved'     => sc_t('dashboard_pages.saved', 'Saved.'),
        'confirmDelete' => sc_t('dashboard_pages.confirm_delete_hall', 'Delete “%1$s”? Its %2$d programme items stay, without a hall.'),
        'failed'    => sc_t('errors.something_wrong', 'Something went wrong. Please try again.'),
        'choose'    => sc_t('dashboard_pages.choose_image', 'Choose an image'),
    )); ?>;
    var esc = function (v) { return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]; }); };
    var post = function (data) { return $.ajax({ url: scDashboard.ajaxurl, type: 'POST', data: $.extend({ nonce: scDashboard.nonce }, data) }); };
    var halls = [];
    var $body = $('#halls-body'), $modal = $('#hallModal'), form = document.getElementById('hall-form');

    function load() {
        post({ action: 'sc_get_halls_overview' }).done(function (res) {
            if (!res.success) { showError(res.data && res.data.message || L.failed); return; }
            halls = res.data.halls;
            render();
            var editId = +(new URLSearchParams(location.search).get('edit') || 0);
            var target = editId && halls.filter(function (x) { return x.id === editId; })[0];
            if (target && !$modal.hasClass('show')) { open(target); history.replaceState(null, '', location.pathname); }
        }).fail(function () { showError(L.failed); });
    }

    function render() {
        $('#halls-count').text(halls.length || '');
        if (!halls.length) {
            $body.html('<div class="w-state"><p class="w-state__title">' + esc(L.empty) + '</p><div class="w-state__actions"><button type="button" class="btn btn-primary" data-add>' + esc(L.add) + '</button></div></div>');
            return;
        }
        $body.html(halls.map(function (h) {
            return '<button type="button" class="w-hall' + (h.is_active ? '' : ' is-inactive') + '" data-id="' + h.id + '">' +
                '<span class="w-hall__photo">' + (h.image_url ? '<img src="' + esc(h.image_url) + '" alt="" loading="lazy">' : '<i class="fa fa-building-o" aria-hidden="true"></i>') + '</span>' +
                '<span class="w-hall__body"><strong>' + esc(h.name) + (h.is_active ? '' : ' <span class="w-tag">' + esc(L.inactive) + '</span>') + '</strong>' +
                '<span class="w-sub">' + esc([h.capacity ? L.seats.replace('%s', h.capacity.toLocaleString('en-US')) : '', h.location].filter(Boolean).join(' · ')) + '</span>' +
                '<span class="w-sub">' + esc(h.items ? L.items.replace('%d', h.items) + (h.events.length ? ' · ' + h.events.join(', ') : '') : L.unused) + '</span></span>' +
                '</button>';
        }).join(''));
    }

    function setPhoto(id, url) {
        form.image.value = id || '';
        $('#h-photo').attr('src', url || '').prop('hidden', !url);
        $('#h-photo-remove').prop('hidden', !url);
    }
    function clearErrors() {
        $(form).find('.has-error').removeClass('has-error');
        $(form).find('.w-field__error').remove();
    }

    function open(h) {
        form.reset();
        clearErrors();
        $('#hall-title').text(h ? L.edit : L.add);
        form.hall_id.value = h ? h.id : '';
        form.name.value = h ? h.name : '';
        form.capacity.value = h && h.capacity ? h.capacity : '';
        form.sort_order.value = h ? h.sort_order : 0;
        form.location.value = h ? h.location || '' : '';
        form.description.value = h ? h.description || '' : '';
        $(form).find('input[type="checkbox"][name="is_active"]').prop('checked', h ? h.is_active : true);
        setPhoto(h ? h.image_id : '', h ? h.image_url : '');
        $('#hall-delete').prop('hidden', !h);
        $modal.modal('show');
    }
    $modal.on('shown.bs.modal', function () { form.name.focus(); });

    var frame = null;
    $('#h-photo-choose').on('click', function () {
        if (!window.wp || !wp.media) { return; }
        if (!frame) {
            frame = wp.media({ title: L.choose, library: { type: 'image' }, multiple: false });
            frame.on('select', function () {
                var a = frame.state().get('selection').first().toJSON();
                setPhoto(a.id, a.sizes && a.sizes.medium ? a.sizes.medium.url : a.url);
            });
        }
        frame.open();
    });
    $('#h-photo-remove').on('click', function () { setPhoto('', ''); });

    $(form).on('submit', function (e) {
        e.preventDefault();
        clearErrors();
        if (!$.trim(form.name.value)) {
            $(form.name).closest('.w-field').addClass('has-error').append($('<p class="w-field__error">').text(L.errName));
            form.name.focus();
            return;
        }
        var data = $(form).serializeArray().reduce(function (o, f) { o[f.name] = f.value; return o; }, {});
        var $btn = $('#hall-save').prop('disabled', true);
        post($.extend({ action: 'sc_save_hall' }, data)).done(function (res) {
            $btn.prop('disabled', false);
            if (!res.success) {
                var errs = res.data && res.data.errors || {};
                Object.keys(errs).forEach(function (k) { $(form).find('[name="' + k + '"]').closest('.w-field').addClass('has-error').append($('<p class="w-field__error">').text(errs[k])); });
                if (!Object.keys(errs).length) { showError(res.data && res.data.message || L.failed); }
                return;
            }
            $modal.modal('hide');
            if (window.toastr) { toastr.success(L.saved); }
            load();
        }).fail(function () { $btn.prop('disabled', false); showError(L.failed); });
    });

    $('#hall-delete').on('click', function () {
        var h = halls.filter(function (x) { return x.id === +form.hall_id.value; })[0];
        if (!h) { return; }
        showDeleteConfirm(L.confirmDelete.replace('%1$s', h.name).replace('%2$d', h.items)).then(function (r) {
            if (!r.isConfirmed) { return; }
            post({ action: 'sc_delete_hall', hall_id: h.id }).done(function (res) {
                if (res.success) { $modal.modal('hide'); load(); } else { showError(res.data && res.data.message || L.failed); }
            }).fail(function () { showError(L.failed); });
        });
    });

    $('#hall-add').on('click', function () { open(null); });
    $body.on('click', '[data-add]', function () { open(null); });
    $body.on('click', '.w-hall', function () { open(halls.filter(function (x) { return x.id === +this.getAttribute('data-id'); }, this)[0]); });

    load();
    if (new URLSearchParams(location.search).get('add') === '1') { open(null); }
});
</script>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
