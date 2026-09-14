<?php
/**
 * Booth type form — shared by booth-type-create.php and booth-type-edit.php.
 * Posts to sc_booth_type_save (inc/admin-dashboard/booths-dashboard.php).
 *
 * Expects: $booth_type (object|null).
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$p = $wpdb->prefix;
$is_edit = !empty($booth_type);
$dashboard_url = home_url('/event-manager-dashboard/');
$t = $is_edit ? $booth_type : (object) array(
    'id' => 0, 'event_id' => isset($_GET['event_id']) ? absint($_GET['event_id']) : 0, 'name' => '', 'name_ar' => '', 'description' => '',
    'booth_category' => 'standard', 'width_meters' => 3, 'depth_meters' => 3, 'base_price' => '', 'deposit_amount' => 0, 'deposit_percentage' => 0,
    'inclusions' => '', 'is_active' => 1, 'color' => '#3B82F6',
);
$inclusions = json_decode((string) $t->inclusions, true);
$inclusions = is_array($inclusions) ? array_values($inclusions) : array();
$deposit_mode = (float) $t->deposit_percentage > 0 ? 'percent' : 'amount';
$deposit_value = $deposit_mode === 'percent' ? (float) $t->deposit_percentage : (float) $t->deposit_amount;
$booth_count = $is_edit ? (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}sc_booths WHERE booth_type_id = %d", $t->id)) : 0;
$events = $wpdb->get_results($wpdb->prepare("SELECT id, title FROM {$p}sc_events WHERE status IN ('publish', 'completed', 'draft') OR id = %d ORDER BY start_date DESC LIMIT 200", (int) $t->event_id));
$currency = get_option('sc_currency_code', 'EGP');
$metres = function ($v) {
    return rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
};
$save_label = $is_edit ? sc_t('dashboard_pages.save_changes', 'Save changes') : sc_t('booths.create_type', 'Create booth type');
$js = function ($value) {
    return wp_json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
};
?>

<div id="main-content">
<div class="container-fluid">
<form id="booth-type-form" novalidate autocomplete="off">
    <input type="hidden" name="id" value="<?php echo (int) $t->id; ?>">
    <input type="hidden" name="is_active" value="0">

    <div class="w-form-head">
        <div>
            <span class="w-form-head__eyebrow"><?php echo esc_html(sc_t('booths.booth_type', 'Booth type')); ?></span>
            <h1 data-w-title><?php echo esc_html($t->name !== '' ? $t->name : sc_t('booths.new_type', 'New booth type')); ?></h1>
            <div class="w-form-head__meta"><span class="w-dirty" data-w-dirty hidden><?php echo esc_html(sc_t('dashboard_pages.unsaved_changes', 'Unsaved changes')); ?></span></div>
        </div>
        <div class="w-form-head__actions">
            <a class="btn btn-secondary" href="<?php echo esc_url($dashboard_url . 'booth-types' . ($t->event_id ? '?event_id=' . (int) $t->event_id : '')); ?>"><?php echo esc_html($is_edit ? sc_t('booths.booth_types', 'Booth types') : sc_t('dashboard_pages.cancel', 'Cancel')); ?></a>
            <button type="submit" class="btn btn-primary w-save-head" data-w-save><?php echo esc_html($save_label); ?></button>
        </div>
    </div>

    <div class="w-errors" data-w-errors hidden></div>

    <div class="w-form-layout w-form-layout--noseq">
        <div class="w-form-main">
            <section class="w-section" aria-labelledby="bt-basics">
                <div class="w-section__head"><h2 id="bt-basics"><?php echo esc_html(sc_t('dashboard_pages.basics', 'Basics')); ?></h2></div>
                <div class="w-fields">
                    <div class="w-field">
                        <label for="bt-event"><?php echo esc_html(sc_t('events.event', 'Event')); ?><span class="w-req" aria-hidden="true">*</span></label>
                        <select class="form-control" id="bt-event" name="event_id" required <?php disabled($booth_count > 0); ?>>
                            <option value=""><?php echo esc_html(sc_t('dashboard_pages.choose_event', '— Choose the event —')); ?></option>
                            <?php foreach ($events as $ev): ?>
                                <option value="<?php echo (int) $ev->id; ?>" <?php selected((int) $t->event_id, (int) $ev->id); ?>><?php echo esc_html($ev->title); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($booth_count): ?>
                            <input type="hidden" name="event_id" value="<?php echo (int) $t->event_id; ?>">
                            <p class="w-field__help"><?php echo esc_html(sc_t('booths.type_event_locked', 'This type already has booths, so it stays on its event.')); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="w-fields w-fields--2">
                        <div class="w-field">
                            <label for="bt-name"><?php echo esc_html(sc_t('dashboard_pages.name', 'Name')); ?><span class="w-req" aria-hidden="true">*</span></label>
                            <input type="text" class="form-control" id="bt-name" name="name" value="<?php echo esc_attr($t->name); ?>" maxlength="255" required placeholder="<?php echo esc_attr(sc_t('booths.name_placeholder', 'e.g. Standard shell scheme')); ?>">
                        </div>
                        <div class="w-field">
                            <label for="bt-name-ar"><?php echo esc_html(sc_t('dashboard_pages.name_ar', 'Name in Arabic')); ?></label>
                            <input type="text" class="form-control" id="bt-name-ar" name="name_ar" value="<?php echo esc_attr((string) $t->name_ar); ?>" dir="rtl" maxlength="255">
                        </div>
                    </div>
                    <div class="w-field">
                        <span class="w-field__label"><?php echo esc_html(sc_t('booths.category', 'Layout')); ?></span>
                        <div class="w-choice" role="radiogroup">
                            <?php foreach (array(
                                'standard'  => sc_t('booths.cat_standard', 'Open on one side'),
                                'corner'    => sc_t('booths.cat_corner', 'Open on two sides'),
                                'peninsula' => sc_t('booths.cat_peninsula', 'Open on three sides'),
                                'island'    => sc_t('booths.cat_island', 'Open on all sides'),
                                'inline'    => sc_t('booths.cat_inline', 'In a row'),
                                'custom'    => sc_t('booths.cat_custom', 'Built to order'),
                            ) as $value => $help): ?>
                                <label class="w-choice__item"><input type="radio" name="booth_category" value="<?php echo esc_attr($value); ?>" <?php checked($t->booth_category, $value); ?>><span class="w-choice__box"><span><?php echo esc_html(sc_booth_categories()[$value]); ?><span class="w-choice__sub"><?php echo esc_html($help); ?></span></span></span></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="w-field">
                        <label for="bt-desc"><?php echo esc_html(sc_t('dashboard_pages.description', 'Description')); ?></label>
                        <textarea class="form-control" id="bt-desc" name="description" rows="3"><?php echo esc_textarea((string) $t->description); ?></textarea>
                    </div>
                </div>
            </section>

            <section class="w-section" aria-labelledby="bt-size">
                <div class="w-section__head"><h2 id="bt-size"><?php echo esc_html(sc_t('booths.size', 'Size')); ?></h2><span class="w-section__hint" id="bt-area"></span></div>
                <div class="w-fields">
                    <div class="w-chiprow" role="group" aria-label="<?php echo esc_attr(sc_t('booths.common_sizes', 'Common sizes')); ?>">
                        <?php foreach (array(array(3, 3), array(3, 4), array(4, 4), array(6, 3), array(6, 6), array(9, 6), array(9, 9)) as $s): ?>
                            <button type="button" class="w-chipbtn" data-size="<?php echo esc_attr($s[0] . 'x' . $s[1]); ?>"><?php echo esc_html($s[0] . ' × ' . $s[1]); ?></button>
                        <?php endforeach; ?>
                    </div>
                    <div class="w-fields w-fields--2">
                        <div class="w-field">
                            <label for="bt-width"><?php echo esc_html(sc_t('booths.width_m', 'Width (m)')); ?><span class="w-req" aria-hidden="true">*</span></label>
                            <input type="number" class="form-control" id="bt-width" name="width_meters" value="<?php echo esc_attr($metres($t->width_meters)); ?>" min="0.5" max="999" step="0.5" inputmode="decimal" required>
                        </div>
                        <div class="w-field">
                            <label for="bt-depth"><?php echo esc_html(sc_t('booths.depth_m', 'Depth (m)')); ?><span class="w-req" aria-hidden="true">*</span></label>
                            <input type="number" class="form-control" id="bt-depth" name="depth_meters" value="<?php echo esc_attr($metres($t->depth_meters)); ?>" min="0.5" max="999" step="0.5" inputmode="decimal" required>
                        </div>
                    </div>
                </div>
            </section>

            <section class="w-section" aria-labelledby="bt-price">
                <div class="w-section__head"><h2 id="bt-price"><?php echo esc_html(sc_t('booths.price', 'Price')); ?></h2></div>
                <div class="w-fields w-fields--2">
                    <div class="w-field">
                        <label for="bt-base"><?php echo esc_html(sprintf(sc_t('booths.price_in', 'Price (%s)'), $currency)); ?></label>
                        <input type="number" class="form-control" id="bt-base" name="base_price" value="<?php echo (float) $t->base_price ? esc_attr((float) $t->base_price) : ''; ?>" min="0" step="0.01" inputmode="decimal">
                        <p class="w-field__help" id="bt-sqm"></p>
                    </div>
                    <div class="w-field">
                        <label for="bt-deposit"><?php echo esc_html(sc_t('booths.deposit', 'Deposit')); ?></label>
                        <div class="w-affix">
                            <input type="number" class="form-control" id="bt-deposit" name="deposit" value="<?php echo $deposit_value ? esc_attr($deposit_value) : ''; ?>" min="0" step="0.01" inputmode="decimal">
                            <select class="form-control" name="deposit_mode" aria-label="<?php echo esc_attr(sc_t('booths.deposit_unit', 'Deposit unit')); ?>">
                                <option value="amount" <?php selected($deposit_mode, 'amount'); ?>><?php echo esc_html($currency); ?></option>
                                <option value="percent" <?php selected($deposit_mode, 'percent'); ?>>%</option>
                            </select>
                        </div>
                        <p class="w-field__help" id="bt-deposit-help"></p>
                    </div>
                </div>
            </section>

            <section class="w-section" aria-labelledby="bt-incl">
                <div class="w-section__head"><h2 id="bt-incl"><?php echo esc_html(sc_t('booths.included', 'What is included')); ?></h2><span class="w-section__hint"><?php echo esc_html(sc_t('booths.included_hint', 'Shown to exhibitors when they choose a booth.')); ?></span></div>
                <div class="w-repeat" id="incl-rows"></div>
                <button type="button" class="btn btn-sm btn-secondary mt-2" id="add-incl"><i class="fa fa-plus" aria-hidden="true"></i> <?php echo esc_html(sc_t('booths.add_item', 'Add item')); ?></button>
            </section>
        </div>

        <aside class="w-form-aside">
            <div class="w-aside-card">
                <span class="w-aside-card__title"><?php echo esc_html(sc_t('dashboard_pages.status', 'Status')); ?></span>
                <label class="w-switch">
                    <input type="checkbox" name="is_active" value="1" <?php checked((int) $t->is_active); ?>>
                    <span class="w-switch__track" aria-hidden="true"></span>
                    <span class="w-switch__text"><strong><?php echo esc_html(sc_t('booths.on_sale', 'On sale')); ?></strong><span><?php echo esc_html(sc_t('booths.on_sale_help', 'Turn off to stop offering this type without deleting it.')); ?></span></span>
                </label>
            </div>
            <div class="w-aside-card">
                <label class="w-aside-card__title" for="bt-color"><?php echo esc_html(sc_t('booths.colour', 'Colour on the floor plan')); ?></label>
                <input type="color" class="form-control w-color" id="bt-color" name="color" value="<?php echo esc_attr($t->color ?: '#3B82F6'); ?>">
            </div>
            <?php if ($is_edit): ?>
            <div class="w-aside-card">
                <span class="w-aside-card__title"><?php echo esc_html(sc_t('booths.booths', 'Booths')); ?></span>
                <p class="mb-2"><?php echo esc_html($booth_count ? sprintf(sc_t('booths.n_booths_of_type', '%s booths use this type.'), number_format_i18n($booth_count)) : sc_t('booths.no_booths_yet', 'No booths of this type yet')); ?></p>
                <a class="btn btn-sm btn-secondary" href="<?php echo esc_url($dashboard_url . 'booths?event_id=' . (int) $t->event_id . '&type_id=' . (int) $t->id . ($booth_count ? '' : '&add=1')); ?>"><?php echo esc_html($booth_count ? sc_t('booths.see_booths', 'See booths') : sc_t('booths.add_booths', 'Add booths')); ?></a>
            </div>
            <div class="w-danger">
                <strong><?php echo esc_html(sc_t('booths.delete_type', 'Delete booth type')); ?></strong>
                <p><?php echo esc_html($booth_count ? sc_t('booths.delete_type_blocked', 'Booths use this type. Delete or move them first, or turn the type off.') : sc_t('booths.delete_type_help', 'Removes the type. This cannot be undone.')); ?></p>
                <button type="button" class="btn btn-sm" id="delete-type" <?php disabled($booth_count > 0); ?>><?php echo esc_html(sc_t('dashboard_pages.delete', 'Delete')); ?></button>
            </div>
            <?php endif; ?>
        </aside>
    </div>

    <div class="w-savebar">
        <span class="w-dirty" data-w-dirty hidden><?php echo esc_html(sc_t('dashboard_pages.unsaved_changes', 'Unsaved changes')); ?></span>
        <button type="submit" class="btn btn-primary" data-w-save><?php echo esc_html($save_label); ?></button>
    </div>
</form>
</div>
</div>

<script>
jQuery(function ($) {
    'use strict';
    var dashboardUrl = <?php echo $js($dashboard_url); ?>;
    var currency = <?php echo $js($currency); ?>;
    var typeId = <?php echo (int) $t->id; ?>;
    var inclusions = <?php echo $js($inclusions); ?>;
    var L = <?php echo $js(array(
        'untitled'  => sc_t('booths.new_type', 'New booth type'),
        'area'      => sc_t('booths.area_x', '%s m² floor space'),
        'perSqm'    => sc_t('booths.per_sqm', '%s per m²'),
        'depositIs' => sc_t('booths.deposit_is', 'Deposit of %s on a booth at full price.'),
        'item'      => sc_t('booths.item', 'Included item'),
        'remove'    => sc_t('dashboard_pages.remove', 'Remove'),
        'errEvent'  => sc_t('dashboard_pages.err_event', 'Choose the event.'),
        'errName'   => sc_t('booths.err_name', 'Enter a name.'),
        'errWidth'  => sc_t('booths.err_width', 'Enter the width in metres.'),
        'errDepth'  => sc_t('booths.err_depth', 'Enter the depth in metres.'),
        'errPct'    => sc_t('booths.err_pct', 'A percentage cannot be over 100.'),
        'saved'     => sc_t('dashboard_pages.saved', 'Saved.'),
        'confirmDelete' => sc_t('booths.confirm_delete_type', 'Delete the booth type %s? This cannot be undone.'),
        'failed'    => sc_t('errors.something_wrong', 'Something went wrong. Please try again.'),
        'saving'    => sc_t('dashboard_pages.saving', 'Saving…'),
        'fixErrors' => sc_t('dashboard_pages.fix_n', 'Fix %d to save'),
        'errorsTitle'   => sc_t('dashboard_pages.errors_title', '%d fields need attention before saving.'),
        'errorTitleOne' => sc_t('dashboard_pages.error_title_one', 'One field needs attention before saving.'),
        'leave'     => sc_t('dashboard_pages.unsaved_leave', 'You have unsaved changes.'),
    )); ?>;
    var esc = function (v) { return String(v == null ? '' : v).replace(/[&<>"']/g, function (ch) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[ch]; }); };
    var formEl = document.getElementById('booth-type-form');
    var fmt = function (n) { return Number(n).toLocaleString('en-US', { maximumFractionDigits: 2 }); };

    function summary() {
        var w = parseFloat($('#bt-width').val()) || 0, d = parseFloat($('#bt-depth').val()) || 0, price = parseFloat($('#bt-base').val()) || 0;
        var area = w * d;
        $('#bt-area').text(area ? L.area.replace('%s', fmt(area)) : '');
        $('#bt-sqm').text(area && price ? L.perSqm.replace('%s', fmt(price / area) + ' ' + currency) : '');
        var dep = parseFloat($('#bt-deposit').val()) || 0, pct = $('[name="deposit_mode"]').val() === 'percent';
        $('#bt-deposit-help').text(dep && price ? L.depositIs.replace('%s', fmt(pct ? price * dep / 100 : dep) + ' ' + currency) : '');
        $('[data-size]').each(function () { $(this).attr('aria-pressed', this.getAttribute('data-size') === w + 'x' + d ? 'true' : 'false'); });
    }
    $(formEl).on('input change', '#bt-width, #bt-depth, #bt-base, #bt-deposit, [name="deposit_mode"]', summary);
    $('[data-size]').on('click', function () {
        var s = this.getAttribute('data-size').split('x');
        $('#bt-width').val(s[0]); $('#bt-depth').val(s[1]).trigger('input');
    });
    summary();

    function row(value) {
        return '<div class="w-repeat__row"><input type="text" class="form-control" name="inclusions[]" value="' + esc(value) + '" placeholder="' + esc(L.item) + '" aria-label="' + esc(L.item) + '" maxlength="120">' +
            '<button type="button" class="w-icon-btn w-icon-btn--danger" data-remove aria-label="' + esc(L.remove) + '">&times;</button></div>';
    }
    $('#incl-rows').html(inclusions.map(row).join(''));
    $('#add-incl').on('click', function () { $('#incl-rows').append(row('')); formEl.dispatchEvent(new Event('input', { bubbles: true })); $('#incl-rows input').last().trigger('focus'); });
    $(formEl).on('click', '[data-remove]', function () { $(this).closest('.w-repeat__row').remove(); formEl.dispatchEvent(new Event('input', { bubbles: true })); });
    $(formEl).on('keydown', '#incl-rows input', function (e) { if (e.key === 'Enter') { e.preventDefault(); $('#add-incl').trigger('click'); } });
    $('#bt-name').on('input', function () { $('[data-w-title]').text($.trim(this.value) || L.untitled); });

    var form = WDForm.create({
        form: formEl,
        i18n: { saving: L.saving, fixErrors: L.fixErrors, errorsTitle: L.errorsTitle, errorTitleOne: L.errorTitleOne, failed: L.failed, leave: L.leave },
        validate: function (v) {
            var e = [];
            if (!v.event_id) { e.push({ field: 'event_id', message: L.errEvent }); }
            if (!$.trim(v.name)) { e.push({ field: 'name', message: L.errName }); }
            if (!(parseFloat(v.width_meters) > 0)) { e.push({ field: 'width_meters', message: L.errWidth }); }
            if (!(parseFloat(v.depth_meters) > 0)) { e.push({ field: 'depth_meters', message: L.errDepth }); }
            if (v.deposit_mode === 'percent' && parseFloat(v.deposit) > 100) { e.push({ field: 'deposit', message: L.errPct }); }
            return e;
        },
        submit: function (fd) {
            fd.append('action', 'sc_booth_type_save');
            fd.append('nonce', scDashboard.nonce);
            return fetch(scDashboard.ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); });
        },
        onSuccess: function (data, api) {
            api.markClean();
            if (data.redirect) {
                try { sessionStorage.setItem('scBoothTypeSaved', data.message); } catch (x) { /* storage blocked */ }
                window.location.href = data.redirect;
                return;
            }
            if (window.toastr) { toastr.success(data.message || L.saved); }
        }
    });
    try {
        var saved = sessionStorage.getItem('scBoothTypeSaved');
        if (saved) { sessionStorage.removeItem('scBoothTypeSaved'); if (window.toastr) { toastr.success(saved); } }
    } catch (x) { /* storage blocked */ }

    $('#delete-type').on('click', function () {
        showDeleteConfirm(L.confirmDelete.replace('%s', $('#bt-name').val())).then(function (r) {
            if (!r.isConfirmed) { return; }
            $.post(scDashboard.ajaxurl, { action: 'sc_booth_type_delete', nonce: scDashboard.nonce, id: typeId })
                .done(function (res) {
                    if (!res.success) { showError(res.data && res.data.message || L.failed); return; }
                    form.markClean();
                    window.location.href = dashboardUrl + 'booth-types?event_id=' + encodeURIComponent($('[name="event_id"]').val());
                })
                .fail(function () { showError(L.failed); });
        });
    });
});
</script>
