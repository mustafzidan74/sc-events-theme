<?php
/**
 * Booth form — shared by booth-create.php and booth-edit.php.
 * Posts to sc_booth_save (inc/admin-dashboard/booths-dashboard.php).
 *
 * Expects: $booth (object|null).
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$p = $wpdb->prefix;
$is_edit = !empty($booth);
$dashboard_url = home_url('/event-manager-dashboard/');
$b = $is_edit ? $booth : (object) array(
    'id' => 0, 'event_id' => isset($_GET['event_id']) ? absint($_GET['event_id']) : 0, 'booth_type_id' => isset($_GET['type_id']) ? absint($_GET['type_id']) : 0,
    'booth_number' => '', 'booth_name' => '', 'booth_name_ar' => '', 'floor_level' => 1, 'custom_width' => null, 'custom_depth' => null, 'custom_price' => null,
    'status' => 'available', 'is_featured' => 0, 'has_electricity' => 1, 'has_water' => 0, 'has_wifi' => 1, 'power_outlets' => 2, 'max_power_kw' => 3, 'notes' => '',
    'current_booking_id' => null,
);

$types = $wpdb->get_results("SELECT t.id, t.event_id, t.name, t.width_meters, t.depth_meters, t.base_price, t.color, e.title AS event_title
    FROM {$p}sc_booth_types t JOIN {$p}sc_events e ON e.id = t.event_id ORDER BY e.start_date DESC, t.sort_order, t.name");
$by_event = array();
foreach ($types as $t) {
    $by_event[$t->event_title][] = $t;
}
if (!$b->booth_type_id && $b->event_id) {
    foreach ($types as $t) {
        if ((int) $t->event_id === (int) $b->event_id) {
            $b->booth_type_id = (int) $t->id;
            break;
        }
    }
}
$booking = $is_edit && $b->current_booking_id ? $wpdb->get_row($wpdb->prepare(
    "SELECT bk.id, bk.booking_ref, bk.status, bk.payment_status, c.id AS company_id, c.company_name FROM {$p}sc_booth_bookings bk LEFT JOIN {$p}sc_company_attendees c ON c.id = bk.company_attendee_id WHERE bk.id = %d AND bk.status IN ('pending', 'confirmed', 'active')",
    (int) $b->current_booking_id
)) : null;
$history = $is_edit ? (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}sc_booth_bookings WHERE booth_id = %d", $b->id)) : 0;
$currency = get_option('sc_currency_code', 'EGP');
$num = function ($v) {
    return $v === null || $v === '' ? '' : rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
};
$status_labels = array('available' => sc_t('booths.available', 'Available'), 'reserved' => sc_t('booths.reserved', 'Reserved'), 'booked' => sc_t('booths.booked', 'Booked'), 'occupied' => sc_t('booths.occupied', 'Occupied'), 'unavailable' => sc_t('booths.closed', 'Closed'));
$save_label = $is_edit ? sc_t('dashboard_pages.save_changes', 'Save changes') : sc_t('booths.create_booth', 'Create booth');
$js = function ($value) {
    return wp_json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
};
?>

<div id="main-content">
<div class="container-fluid">
<form id="booth-form" novalidate autocomplete="off">
    <input type="hidden" name="id" value="<?php echo (int) $b->id; ?>">

    <div class="w-form-head">
        <div>
            <span class="w-form-head__eyebrow"><?php echo esc_html(sc_t('booths.booth', 'Booth')); ?></span>
            <h1 data-w-title><?php echo esc_html($b->booth_number !== '' ? sprintf(sc_t('booths.booth_x', 'Booth %s'), $b->booth_number) : sc_t('booths.new_booth', 'New booth')); ?></h1>
            <div class="w-form-head__meta">
                <?php if ($is_edit): ?><span class="w-tag"><?php echo esc_html($status_labels[$b->status] ?? $b->status); ?></span><?php endif; ?>
                <span class="w-dirty" data-w-dirty hidden><?php echo esc_html(sc_t('dashboard_pages.unsaved_changes', 'Unsaved changes')); ?></span>
            </div>
        </div>
        <div class="w-form-head__actions">
            <a class="btn btn-secondary" href="<?php echo esc_url($dashboard_url . 'booths' . ($b->event_id ? '?event_id=' . (int) $b->event_id : '')); ?>"><?php echo esc_html($is_edit ? sc_t('booths.all_booths', 'Booths') : sc_t('dashboard_pages.cancel', 'Cancel')); ?></a>
            <button type="submit" class="btn btn-primary w-save-head" data-w-save><?php echo esc_html($save_label); ?></button>
        </div>
    </div>

    <div class="w-errors" data-w-errors hidden></div>

    <?php if (!$types): ?>
        <div class="w-state">
            <strong><?php echo esc_html(sc_t('booths.no_types_anywhere', 'Create a booth type first')); ?></strong>
            <p><?php echo esc_html(sc_t('booths.need_type_first', 'This event has no booth types yet. Booths take their size and price from a type.')); ?></p>
            <a class="btn btn-primary" href="<?php echo esc_url($dashboard_url . 'booth-type-create' . ($b->event_id ? '?event_id=' . (int) $b->event_id : '')); ?>"><?php echo esc_html(sc_t('booths.add_type', 'Add booth type')); ?></a>
        </div>
    <?php else: ?>
    <div class="w-form-layout w-form-layout--noseq">
        <div class="w-form-main">
            <section class="w-section" aria-labelledby="bo-basics">
                <div class="w-section__head"><h2 id="bo-basics"><?php echo esc_html(sc_t('dashboard_pages.basics', 'Basics')); ?></h2></div>
                <div class="w-fields">
                    <div class="w-field">
                        <label for="bo-type"><?php echo esc_html(sc_t('booths.booth_type', 'Booth type')); ?><span class="w-req" aria-hidden="true">*</span></label>
                        <select class="form-control" id="bo-type" name="booth_type_id" required>
                            <option value=""><?php echo esc_html(sc_t('booths.choose_type', '— Choose the booth type —')); ?></option>
                            <?php foreach ($by_event as $title => $list): ?>
                                <optgroup label="<?php echo esc_attr($title); ?>">
                                    <?php foreach ($list as $t): ?>
                                        <option value="<?php echo (int) $t->id; ?>" <?php selected((int) $b->booth_type_id, (int) $t->id); ?>><?php echo esc_html($t->name); ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                        <p class="w-field__help" id="bo-type-help"></p>
                    </div>
                    <div class="w-fields w-fields--3">
                        <div class="w-field">
                            <label for="bo-number"><?php echo esc_html(sc_t('booths.number', 'Booth number')); ?><span class="w-req" aria-hidden="true">*</span></label>
                            <input type="text" class="form-control w-ltr" id="bo-number" name="booth_number" value="<?php echo esc_attr($b->booth_number); ?>" maxlength="50" required placeholder="A12">
                        </div>
                        <div class="w-field">
                            <label for="bo-floor"><?php echo esc_html(sc_t('booths.floor', 'Floor')); ?></label>
                            <input type="number" class="form-control" id="bo-floor" name="floor_level" value="<?php echo (int) $b->floor_level; ?>" min="0" max="20" inputmode="numeric">
                        </div>
                        <label class="w-check-line align-self-end mb-2"><input type="hidden" name="is_featured" value="0"><input type="checkbox" name="is_featured" value="1" <?php checked((int) $b->is_featured); ?>> <?php echo esc_html(sc_t('booths.featured_prime', 'Featured / prime spot')); ?></label>
                    </div>
                    <div class="w-fields w-fields--2">
                        <div class="w-field">
                            <label for="bo-name"><?php echo esc_html(sc_t('booths.display_name', 'Name on the plan')); ?></label>
                            <input type="text" class="form-control" id="bo-name" name="booth_name" value="<?php echo esc_attr((string) $b->booth_name); ?>" maxlength="255" placeholder="<?php echo esc_attr(sc_t('booths.display_name_ph', 'e.g. Next to the main stage')); ?>">
                        </div>
                        <div class="w-field">
                            <label for="bo-name-ar"><?php echo esc_html(sc_t('dashboard_pages.name_ar', 'Name in Arabic')); ?></label>
                            <input type="text" class="form-control" id="bo-name-ar" name="booth_name_ar" value="<?php echo esc_attr((string) $b->booth_name_ar); ?>" dir="rtl" maxlength="255">
                        </div>
                    </div>
                </div>
            </section>

            <section class="w-section" aria-labelledby="bo-size">
                <div class="w-section__head"><h2 id="bo-size"><?php echo esc_html(sc_t('booths.size_price', 'Size and price')); ?></h2><span class="w-section__hint"><?php echo esc_html(sc_t('booths.override_hint', 'Leave empty to use the booth type.')); ?></span></div>
                <div class="w-fields w-fields--3">
                    <div class="w-field">
                        <label for="bo-w"><?php echo esc_html(sc_t('booths.width_m', 'Width (m)')); ?></label>
                        <input type="number" class="form-control" id="bo-w" name="custom_width" value="<?php echo esc_attr($num($b->custom_width)); ?>" min="0.5" max="999" step="0.5" inputmode="decimal">
                    </div>
                    <div class="w-field">
                        <label for="bo-d"><?php echo esc_html(sc_t('booths.depth_m', 'Depth (m)')); ?></label>
                        <input type="number" class="form-control" id="bo-d" name="custom_depth" value="<?php echo esc_attr($num($b->custom_depth)); ?>" min="0.5" max="999" step="0.5" inputmode="decimal">
                    </div>
                    <div class="w-field">
                        <label for="bo-price"><?php echo esc_html(sprintf(sc_t('booths.price_in', 'Price (%s)'), $currency)); ?></label>
                        <input type="number" class="form-control" id="bo-price" name="custom_price" value="<?php echo esc_attr($num($b->custom_price)); ?>" min="0" step="0.01" inputmode="decimal">
                    </div>
                </div>
            </section>

            <section class="w-section" aria-labelledby="bo-services">
                <div class="w-section__head"><h2 id="bo-services"><?php echo esc_html(sc_t('booths.services', 'Services')); ?></h2></div>
                <div class="w-fields">
                    <div class="w-fields w-fields--3">
                        <label class="w-switch"><input type="hidden" name="has_electricity" value="0"><input type="checkbox" name="has_electricity" value="1" <?php checked((int) $b->has_electricity); ?>><span class="w-switch__track" aria-hidden="true"></span><span class="w-switch__text"><strong><?php echo esc_html(sc_t('booths.power', 'Power')); ?></strong></span></label>
                        <label class="w-switch"><input type="hidden" name="has_water" value="0"><input type="checkbox" name="has_water" value="1" <?php checked((int) $b->has_water); ?>><span class="w-switch__track" aria-hidden="true"></span><span class="w-switch__text"><strong><?php echo esc_html(sc_t('booths.water', 'Water')); ?></strong></span></label>
                        <label class="w-switch"><input type="hidden" name="has_wifi" value="0"><input type="checkbox" name="has_wifi" value="1" <?php checked((int) $b->has_wifi); ?>><span class="w-switch__track" aria-hidden="true"></span><span class="w-switch__text"><strong><?php echo esc_html(sc_t('booths.wifi', 'Wi-Fi')); ?></strong></span></label>
                    </div>
                    <div class="w-fields w-fields--2" data-w-show-if="has_electricity:1">
                        <div class="w-field">
                            <label for="bo-outlets"><?php echo esc_html(sc_t('booths.outlets', 'Power outlets')); ?></label>
                            <input type="number" class="form-control" id="bo-outlets" name="power_outlets" value="<?php echo (int) $b->power_outlets; ?>" min="0" max="99" inputmode="numeric">
                        </div>
                        <div class="w-field">
                            <label for="bo-kw"><?php echo esc_html(sc_t('booths.max_kw', 'Maximum load (kW)')); ?></label>
                            <input type="number" class="form-control" id="bo-kw" name="max_power_kw" value="<?php echo esc_attr($num($b->max_power_kw)); ?>" min="0" max="999" step="0.5" inputmode="decimal">
                        </div>
                    </div>
                    <div class="w-field">
                        <label for="bo-notes"><?php echo esc_html(sc_t('dashboard_pages.notes', 'Notes')); ?></label>
                        <textarea class="form-control" id="bo-notes" name="notes" rows="2"><?php echo esc_textarea((string) $b->notes); ?></textarea>
                        <p class="w-field__help"><?php echo esc_html(sc_t('dashboard_pages.notes_private', 'Only visible in the dashboard.')); ?></p>
                    </div>
                </div>
            </section>
        </div>

        <aside class="w-form-aside">
            <div class="w-aside-card">
                <span class="w-aside-card__title"><?php echo esc_html(sc_t('booths.availability', 'Availability')); ?></span>
                <?php if ($booking): ?>
                    <p class="mb-2"><?php echo esc_html(sprintf(sc_t('booths.held_by', 'Held by %1$s (booking %2$s).'), $booking->company_name, $booking->booking_ref)); ?></p>
                    <input type="hidden" name="availability" value="available">
                    <a class="btn btn-sm btn-secondary" href="<?php echo esc_url($dashboard_url . 'booth-booking-edit?id=' . (int) $booking->id); ?>"><?php echo esc_html(sc_t('booths.open_the_booking', 'Open the booking')); ?></a>
                <?php else: ?>
                    <div class="w-choice w-choice--stack" role="radiogroup">
                        <label class="w-choice__item"><input type="radio" name="availability" value="available" <?php checked($b->status !== 'unavailable'); ?>><span class="w-choice__box"><span><?php echo esc_html(sc_t('booths.open_for_booking', 'Open for booking')); ?></span></span></label>
                        <label class="w-choice__item"><input type="radio" name="availability" value="unavailable" <?php checked($b->status, 'unavailable'); ?>><span class="w-choice__box"><span><?php echo esc_html(sc_t('booths.closed', 'Closed')); ?><span class="w-choice__sub"><?php echo esc_html(sc_t('booths.closed_help', 'Kept on the plan, not offered')); ?></span></span></span></label>
                    </div>
                    <?php if ($is_edit && $b->status === 'available'): ?>
                        <a class="btn btn-sm btn-primary mt-3" href="<?php echo esc_url($dashboard_url . 'booth-booking-create?booth_id=' . (int) $b->id); ?>"><?php echo esc_html(sc_t('booths.book', 'Book for a company')); ?></a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php if ($is_edit): ?>
            <div class="w-danger">
                <strong><?php echo esc_html(sc_t('booths.delete_booth', 'Delete booth')); ?></strong>
                <p><?php echo esc_html($history ? sc_t('booths.delete_booth_blocked', 'This booth has bookings on record, so it is kept. Close it instead.') : sc_t('booths.delete_booth_help', 'Removes the booth from the list and the floor plan.')); ?></p>
                <button type="button" class="btn btn-sm" id="delete-booth" <?php disabled($history > 0); ?>><?php echo esc_html(sc_t('dashboard_pages.delete', 'Delete')); ?></button>
            </div>
            <?php endif; ?>
        </aside>
    </div>

    <div class="w-savebar">
        <span class="w-dirty" data-w-dirty hidden><?php echo esc_html(sc_t('dashboard_pages.unsaved_changes', 'Unsaved changes')); ?></span>
        <button type="submit" class="btn btn-primary" data-w-save><?php echo esc_html($save_label); ?></button>
    </div>
    <?php endif; ?>
</form>
</div>
</div>

<?php if ($types): ?>
<script>
jQuery(function ($) {
    'use strict';
    var dashboardUrl = <?php echo $js($dashboard_url); ?>;
    var currency = <?php echo $js($currency); ?>;
    var boothId = <?php echo (int) $b->id; ?>;
    var types = <?php echo $js(array_reduce($types, function ($c, $t) { $c[(int) $t->id] = array('event' => (int) $t->event_id, 'w' => (float) $t->width_meters, 'd' => (float) $t->depth_meters, 'price' => (float) $t->base_price); return $c; }, array())); ?>;
    var L = <?php echo $js(array(
        'untitled'  => sc_t('booths.new_booth', 'New booth'),
        'boothX'    => sc_t('booths.booth_x', 'Booth %s'),
        'typeHelp'  => sc_t('booths.type_help', '%1$s × %2$s m at %3$s'),
        'errType'   => sc_t('booths.err_type', 'Choose the booth type.'),
        'errNumber' => sc_t('booths.err_number', 'Enter the booth number.'),
        'saved'     => sc_t('dashboard_pages.saved', 'Saved.'),
        'confirmDelete' => sc_t('booths.confirm_delete_booth', 'Delete booth %s? This cannot be undone.'),
        'failed'    => sc_t('errors.something_wrong', 'Something went wrong. Please try again.'),
        'saving'    => sc_t('dashboard_pages.saving', 'Saving…'),
        'fixErrors' => sc_t('dashboard_pages.fix_n', 'Fix %d to save'),
        'errorsTitle'   => sc_t('dashboard_pages.errors_title', '%d fields need attention before saving.'),
        'errorTitleOne' => sc_t('dashboard_pages.error_title_one', 'One field needs attention before saving.'),
        'leave'     => sc_t('dashboard_pages.unsaved_leave', 'You have unsaved changes.'),
    )); ?>;
    var formEl = document.getElementById('booth-form');
    var fmt = function (n) { return Number(n).toLocaleString('en-US', { maximumFractionDigits: 2 }); };

    function typeHelp() {
        var t = types[$('#bo-type').val()];
        $('#bo-type-help').text(t ? L.typeHelp.replace('%1$s', fmt(t.w)).replace('%2$s', fmt(t.d)).replace('%3$s', fmt(t.price) + ' ' + currency) : '');
        $('#bo-w').attr('placeholder', t ? fmt(t.w) : '');
        $('#bo-d').attr('placeholder', t ? fmt(t.d) : '');
        $('#bo-price').attr('placeholder', t ? fmt(t.price) : '');
    }
    $('#bo-type').on('change', typeHelp);
    typeHelp();
    $('#bo-number').on('input', function () {
        var v = $.trim(this.value).toUpperCase();
        $('[data-w-title]').text(v ? L.boothX.replace('%s', v) : L.untitled);
    });

    var form = WDForm.create({
        form: formEl,
        i18n: { saving: L.saving, fixErrors: L.fixErrors, errorsTitle: L.errorsTitle, errorTitleOne: L.errorTitleOne, failed: L.failed, leave: L.leave },
        validate: function (v) {
            var e = [];
            if (!v.booth_type_id) { e.push({ field: 'booth_type_id', message: L.errType }); }
            if (!$.trim(v.booth_number)) { e.push({ field: 'booth_number', message: L.errNumber }); }
            return e;
        },
        submit: function (fd) {
            fd.append('action', 'sc_booth_save');
            fd.append('nonce', scDashboard.nonce);
            return fetch(scDashboard.ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); });
        },
        onSuccess: function (data, api) {
            api.markClean();
            if (data.redirect) {
                try { sessionStorage.setItem('scBoothSaved', data.message); } catch (x) { /* storage blocked */ }
                window.location.href = data.redirect;
                return;
            }
            if (window.toastr) { toastr.success(data.message || L.saved); }
            $('#bo-number').val(String($('#bo-number').val()).toUpperCase().replace(/\s+/g, ''));
        }
    });
    try {
        var saved = sessionStorage.getItem('scBoothSaved');
        if (saved) { sessionStorage.removeItem('scBoothSaved'); if (window.toastr) { toastr.success(saved); } }
    } catch (x) { /* storage blocked */ }

    $('#delete-booth').on('click', function () {
        showDeleteConfirm(L.confirmDelete.replace('%s', $('#bo-number').val())).then(function (r) {
            if (!r.isConfirmed) { return; }
            $.post(scDashboard.ajaxurl, { action: 'sc_booths_bulk', nonce: scDashboard.nonce, op: 'delete', ids: [boothId] })
                .done(function (res) {
                    if (!res.success || !res.data.done) { showError(res.data && res.data.message || L.failed); return; }
                    form.markClean();
                    window.location.href = dashboardUrl + 'booths?event_id=' + (types[$('#bo-type').val()] || {}).event;
                })
                .fail(function () { showError(L.failed); });
        });
    });
});
</script>
<?php endif; ?>
