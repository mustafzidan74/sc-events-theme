<?php
/**
 * Coupon form — shared by coupon-create.php and coupon-edit.php. Posts to sc_save_coupon.
 *
 * Only settings registration enforces are offered: code, active, discount,
 * event, ticket type, usage limit and expiry (see sc_find_coupon()).
 *
 * Expects: $coupon_post (WP_Post|null).
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$is_edit = !empty($coupon_post);
$id = $is_edit ? (int) $coupon_post->ID : 0;
$meta = function ($key, $default = '') use ($id) {
    if (!$id) {
        return $default;
    }
    $value = get_post_meta($id, $key, true);
    return $value === '' ? $default : $value;
};
$c = array(
    'code'          => $is_edit ? $coupon_post->post_title : '',
    'note'          => $is_edit ? $coupon_post->post_content : '',
    'active'        => $is_edit ? $coupon_post->post_status === 'publish' : true,
    'type'          => $meta('discount_type', 'percentage') === 'percentage' ? 'percentage' : 'fixed',
    'value'         => (float) $meta('discount_value', 100),
    'event_id'      => (int) $meta('event_id', isset($_GET['event_id']) ? absint($_GET['event_id']) : 0),
    'category_id'   => (int) $meta('category_id', 0) ?: SC_Coupon_Category::DEFAULT_ID,
    'ticket_filter' => in_array($meta('ticket_type_filter'), array('general', 'competitor'), true) ? $meta('ticket_type_filter') : 'all',
    'usage_limit'   => (int) $meta('usage_limit', $is_edit ? 0 : 1),
    'usage_count'   => (int) $meta('usage_count', 0),
    'expiry'        => substr((string) $meta('expiry_date'), 0, 10),
);
$choice = $c['type'] === 'percentage' && $c['value'] >= 100 ? 'free' : $c['type'];

$events = $wpdb->get_results($wpdb->prepare(
    "SELECT id, title, start_date FROM {$wpdb->prefix}sc_events WHERE status IN ('publish', 'completed', 'draft') OR id = %d ORDER BY start_date DESC LIMIT 200",
    $c['event_id']
));
$categories = SC_Coupon_Category::get_all();
$holders = $is_edit ? $wpdb->get_results($wpdb->prepare(
    "SELECT id, name, email, status, created_at FROM {$wpdb->prefix}sc_attendees WHERE coupon_code = %s ORDER BY id DESC LIMIT 20",
    $c['code']
)) : array();
$holders_total = $is_edit ? (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}sc_attendees WHERE coupon_code = %s", $c['code'])) : 0;

$dashboard_url = home_url('/event-manager-dashboard/');
$currency = get_option('sc_currency_code', 'EGP');
$locked = $is_edit && $c['usage_count'] > 0;
$save_label = $is_edit ? sc_t('dashboard_pages.save_changes', 'Save changes') : sc_t('dashboard_pages.create_coupon', 'Create coupon');
$js = function ($value) {
    return wp_json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
};
?>

<div id="main-content">
<div class="container-fluid">
<form id="coupon-form" novalidate autocomplete="off">
    <input type="hidden" name="coupon_id" value="<?php echo (int) $id; ?>">

    <div class="w-form-head">
        <div>
            <span class="w-form-head__eyebrow"><?php echo esc_html($is_edit ? sc_t('dashboard_pages.coupon', 'Coupon') : sc_t('dashboard_pages.new_coupon', 'New coupon')); ?></span>
            <h1 class="w-mono" data-w-title><?php echo esc_html($c['code'] !== '' ? $c['code'] : sc_t('dashboard_pages.new_coupon', 'New coupon')); ?></h1>
            <div class="w-form-head__meta">
                <?php if ($is_edit): ?>
                    <span class="w-tag <?php echo $c['active'] ? 'w-tag--teal' : ''; ?>"><?php echo esc_html($c['active'] ? sc_t('dashboard_pages.active', 'Active') : sc_t('dashboard_pages.inactive', 'Inactive')); ?></span>
                    <span class="w-sub"><?php echo esc_html(sprintf(sc_t('dashboard_pages.uses_of', '%1$s of %2$s uses'), number_format_i18n($c['usage_count']), $c['usage_limit'] ? number_format_i18n($c['usage_limit']) : '∞')); ?></span>
                <?php endif; ?>
                <span class="w-dirty" data-w-dirty hidden><?php echo esc_html(sc_t('dashboard_pages.unsaved_changes', 'Unsaved changes')); ?></span>
            </div>
        </div>
        <div class="w-form-head__actions">
            <a class="btn btn-secondary" href="<?php echo esc_url($dashboard_url . 'coupons'); ?>"><?php echo esc_html($is_edit ? sc_t('dashboard_pages.coupons_management', 'Coupons') : sc_t('dashboard_pages.cancel', 'Cancel')); ?></a>
            <button type="submit" class="btn btn-primary w-save-head" data-w-save><?php echo esc_html($save_label); ?></button>
        </div>
    </div>

    <div class="w-errors" data-w-errors hidden></div>

    <div class="w-form-layout w-form-layout--noseq">
        <div class="w-form-main">
            <section class="w-section" aria-labelledby="cp-code-h">
                <div class="w-section__head"><h2 id="cp-code-h"><?php echo esc_html(sc_t('dashboard_pages.code', 'Code')); ?></h2></div>
                <div class="w-fields">
                    <div class="w-field">
                        <label for="cp-code"><?php echo esc_html(sc_t('dashboard_pages.coupon_code', 'Coupon code')); ?><span class="w-req" aria-hidden="true">*</span></label>
                        <div class="w-affix">
                            <input type="text" class="form-control w-ltr w-mono w-upper" id="cp-code" name="code" value="<?php echo esc_attr($c['code']); ?>" maxlength="50" required spellcheck="false" autocapitalize="characters"<?php echo $locked ? ' readonly aria-describedby="cp-code-lock"' : ''; ?>>
                            <?php if (!$locked): ?>
                            <button type="button" class="w-affix__btn" id="cp-generate"><?php echo esc_html(sc_t('dashboard_pages.generate', 'Generate')); ?></button>
                            <?php endif; ?>
                        </div>
                        <p class="w-field__help" id="cp-code-lock"><?php echo esc_html($locked
                            ? sc_t('dashboard_pages.code_locked', 'This code has been used, so it can’t be changed — registrations keep the code they used.')
                            : sc_t('dashboard_pages.code_help', 'Letters, numbers, dashes and underscores. Saved in capitals; people can type it in any case.')); ?></p>
                    </div>
                </div>
            </section>

            <section class="w-section" aria-labelledby="cp-discount-h">
                <div class="w-section__head"><h2 id="cp-discount-h"><?php echo esc_html(sc_t('dashboard_pages.discount', 'Discount')); ?></h2></div>
                <div class="w-fields">
                    <div class="w-choice" role="radiogroup" aria-labelledby="cp-discount-h">
                        <?php foreach (array(
                            'free'       => array(sc_t('dashboard_pages.free_ticket', 'Free ticket'), sc_t('dashboard_pages.free_ticket_help', '100% off')),
                            'percentage' => array(sc_t('dashboard_pages.percentage', 'Percentage'), sc_t('dashboard_pages.percentage_help', 'e.g. 25% off')),
                            'fixed'      => array(sc_t('dashboard_pages.fixed_amount', 'Fixed amount'), sprintf(sc_t('dashboard_pages.fixed_amount_help', '%s off the price'), $currency)),
                        ) as $value => $labels): ?>
                        <label class="w-choice__item"><input type="radio" name="discount_type" value="<?php echo esc_attr($value); ?>" <?php checked($choice, $value); ?>><span class="w-choice__box"><span><?php echo esc_html($labels[0]); ?><span class="w-choice__sub"><?php echo esc_html($labels[1]); ?></span></span></span></label>
                        <?php endforeach; ?>
                    </div>
                    <div class="w-field" data-w-show-if="discount_type:percentage,fixed">
                        <label for="cp-value"><span data-unit-label><?php echo esc_html(sc_t('dashboard_pages.discount_value', 'Discount')); ?></span><span class="w-req" aria-hidden="true">*</span></label>
                        <input type="number" class="form-control" id="cp-value" name="discount_value" value="<?php echo $choice === 'free' ? '' : esc_attr($c['value']); ?>" min="0" step="0.01" inputmode="decimal">
                    </div>
                </div>
            </section>

            <section class="w-section" aria-labelledby="cp-where-h">
                <div class="w-section__head"><h2 id="cp-where-h"><?php echo esc_html(sc_t('dashboard_pages.where_it_works', 'Where it works')); ?></h2></div>
                <div class="w-fields w-fields--2">
                    <div class="w-field">
                        <label for="cp-event"><?php echo esc_html(sc_t('events.event', 'Event')); ?></label>
                        <select class="form-control" id="cp-event" name="event_id">
                            <option value="0"><?php echo esc_html(sc_t('dashboard_pages.valid_every_event', 'Valid for every event')); ?></option>
                            <?php foreach ($events as $ev): ?>
                                <option value="<?php echo (int) $ev->id; ?>" <?php selected($c['event_id'], (int) $ev->id); ?>><?php echo esc_html($ev->title); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="w-field">
                        <label for="cp-ticket"><?php echo esc_html(sc_t('dashboard_pages.ticket_type', 'Ticket type')); ?></label>
                        <select class="form-control" id="cp-ticket" name="ticket_type_filter">
                            <option value="all" <?php selected($c['ticket_filter'], 'all'); ?>><?php echo esc_html(sc_t('dashboard_pages.all_tickets', 'All tickets')); ?></option>
                            <option value="general" <?php selected($c['ticket_filter'], 'general'); ?>><?php echo esc_html(sc_t('dashboard_pages.general_tickets_only', 'General tickets only')); ?></option>
                            <option value="competitor" <?php selected($c['ticket_filter'], 'competitor'); ?>><?php echo esc_html(sc_t('dashboard_pages.competitor_tickets_only', 'Competitor tickets only')); ?></option>
                        </select>
                    </div>
                </div>
            </section>

            <section class="w-section" aria-labelledby="cp-limits-h">
                <div class="w-section__head"><h2 id="cp-limits-h"><?php echo esc_html(sc_t('dashboard_pages.limits', 'Limits')); ?></h2></div>
                <div class="w-fields w-fields--2">
                    <div class="w-field">
                        <label for="cp-limit"><?php echo esc_html(sc_t('dashboard_pages.usage_limit', 'Usage limit')); ?></label>
                        <input type="number" class="form-control" id="cp-limit" name="usage_limit" value="<?php echo $c['usage_limit'] ? (int) $c['usage_limit'] : ''; ?>" min="0" step="1" inputmode="numeric" placeholder="<?php echo esc_attr(sc_t('dashboard_pages.unlimited', 'Unlimited')); ?>">
                        <p class="w-field__help"><?php echo esc_html(sc_t('dashboard_pages.usage_limit_help', 'How many registrations can use it. 1 = one person. Leave empty for unlimited.')); ?></p>
                    </div>
                    <div class="w-field">
                        <label for="cp-expiry"><?php echo esc_html(sc_t('dashboard_pages.expires', 'Expires')); ?></label>
                        <input type="date" class="form-control" id="cp-expiry" name="expiry_date" value="<?php echo esc_attr($c['expiry']); ?>">
                        <p class="w-field__help"><?php echo esc_html(sc_t('dashboard_pages.expiry_help', 'Works through the end of this day. Leave empty to never expire.')); ?></p>
                    </div>
                </div>
            </section>

            <section class="w-section" aria-labelledby="cp-org-h">
                <div class="w-section__head"><h2 id="cp-org-h"><?php echo esc_html(sc_t('dashboard_pages.organise', 'Organise')); ?></h2></div>
                <div class="w-fields">
                    <div class="w-field">
                        <label for="cp-category"><?php echo esc_html(sc_t('dashboard_pages.category', 'Category')); ?></label>
                        <select class="form-control" id="cp-category" name="category_id">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo (int) $cat->id; ?>" <?php selected($c['category_id'], (int) $cat->id); ?>><?php echo esc_html($cat->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="w-field">
                        <label for="cp-note"><?php echo esc_html(sc_t('dashboard_pages.note', 'Note')); ?></label>
                        <textarea class="form-control" id="cp-note" name="note" rows="2" placeholder="<?php echo esc_attr(sc_t('dashboard_pages.coupon_note_placeholder', 'Who it’s for, e.g. “Sponsor guest — Dr. Ahmed”')); ?>"><?php echo esc_textarea($c['note']); ?></textarea>
                        <p class="w-field__help"><?php echo esc_html(sc_t('dashboard_pages.notes_private', 'Only visible in the dashboard.')); ?></p>
                    </div>
                </div>
            </section>
        </div>

        <aside class="w-form-aside">
            <div class="w-aside-card">
                <span class="w-aside-card__title"><?php echo esc_html(sc_t('dashboard_pages.status', 'Status')); ?></span>
                <div class="w-choice w-choice--stack" role="radiogroup">
                    <label class="w-choice__item"><input type="radio" name="is_active" value="1" <?php checked($c['active']); ?>><span class="w-choice__box"><span><?php echo esc_html(sc_t('dashboard_pages.active', 'Active')); ?><span class="w-choice__sub"><?php echo esc_html(sc_t('dashboard_pages.coupon_active_help', 'Works at registration')); ?></span></span></span></label>
                    <label class="w-choice__item"><input type="radio" name="is_active" value="0" <?php checked(!$c['active']); ?>><span class="w-choice__box"><span><?php echo esc_html(sc_t('dashboard_pages.inactive', 'Inactive')); ?><span class="w-choice__sub"><?php echo esc_html(sc_t('dashboard_pages.coupon_inactive_help', 'Rejected until you turn it back on')); ?></span></span></span></label>
                </div>
            </div>

            <?php if ($is_edit): ?>
            <div class="w-aside-card">
                <span class="w-aside-card__title"><?php echo esc_html(sprintf(sc_t('dashboard_pages.registrations_n', 'Registrations (%s)'), number_format_i18n($holders_total))); ?></span>
                <?php if ($holders): ?>
                    <ul class="w-history">
                        <?php foreach ($holders as $h): ?>
                        <li><a href="<?php echo esc_url($dashboard_url . 'attendee-edit?id=' . (int) $h->id); ?>"><?php echo esc_html($h->name); ?></a><?php echo $h->status !== 'active' ? ' <span class="w-tag">' . esc_html(sc_t('dashboard_pages.status_cancelled', 'Cancelled')) . '</span>' : ''; ?><span class="w-sub w-ltr"><?php echo esc_html($h->email . ' · ' . mysql2date('j M Y', $h->created_at)); ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if ($holders_total > count($holders)): ?>
                        <a class="d-inline-block mt-2" href="<?php echo esc_url($dashboard_url . 'attendees?coupon_code=' . rawurlencode($c['code'])); ?>"><?php echo esc_html(sc_t('dashboard_pages.see_all_registrations', 'See all registrations')); ?></a>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="w-field__help mb-0"><?php echo esc_html(sc_t('dashboard_pages.coupon_not_used', 'Nobody has registered with this code yet.')); ?></p>
                <?php endif; ?>
                <?php if ($holders_total !== $c['usage_count']): ?>
                    <p class="w-field__help mt-2 mb-0"><?php echo esc_html(sprintf(sc_t('dashboard_pages.uses_mismatch', 'The use count says %1$s. “Recount uses” on the coupons list sets it from active registrations.'), number_format_i18n($c['usage_count']))); ?></p>
                <?php endif; ?>
            </div>

            <div class="w-danger">
                <strong><?php echo esc_html(sc_t('dashboard_pages.delete_coupon', 'Delete coupon')); ?></strong>
                <p><?php echo esc_html(sc_t('dashboard_pages.delete_coupon_help', 'The code stops working. People who already registered with it keep their registration. To pause it instead, set it to Inactive.')); ?></p>
                <button type="button" class="btn btn-sm" id="delete-coupon"><?php echo esc_html(sc_t('dashboard_pages.delete', 'Delete')); ?></button>
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

    var isEdit = <?php echo $is_edit ? 'true' : 'false'; ?>;
    var couponId = <?php echo (int) $id; ?>;
    var dashboardUrl = <?php echo $js($dashboard_url); ?>;
    var L = <?php echo $js(array(
        'untitled'    => sc_t('dashboard_pages.new_coupon', 'New coupon'),
        'percent'     => sc_t('dashboard_pages.percent_off', 'Percent off'),
        'amount'      => sprintf(sc_t('dashboard_pages.amount_off', 'Amount off (%s)'), $currency),
        'errCode'     => sc_t('dashboard_pages.err_code', 'Enter a code.'),
        'errCodeChars'=> sc_t('dashboard_pages.err_code_chars', 'Use 3–50 letters, numbers, dashes or underscores.'),
        'errValue'    => sc_t('dashboard_pages.err_discount_value', 'Enter the discount.'),
        'errPercent'  => sc_t('dashboard_pages.err_percent', 'A percentage is between 1 and 100.'),
        'saved'       => sc_t('dashboard_pages.saved', 'Saved.'),
        'created'     => sc_t('dashboard_pages.coupon_created', 'Coupon created.'),
        'confirmDelete' => sc_t('dashboard_pages.confirm_delete_coupon', 'Delete %s? Anyone holding this code can no longer register with it. This cannot be undone.'),
        'failed'      => sc_t('errors.something_wrong', 'Something went wrong. Please try again.'),
        'saving'      => sc_t('dashboard_pages.saving', 'Saving…'),
        'fixErrors'   => sc_t('dashboard_pages.fix_n', 'Fix %d to save'),
        'errorsTitle' => sc_t('dashboard_pages.errors_title', '%d fields need attention before saving.'),
        'errorTitleOne' => sc_t('dashboard_pages.error_title_one', 'One field needs attention before saving.'),
        'leave'       => sc_t('dashboard_pages.unsaved_leave', 'You have unsaved changes.'),
    )); ?>;
    var formEl = document.getElementById('coupon-form');
    var $code = $('#cp-code');

    function unitLabel() {
        var type = $(formEl).find('[name="discount_type"]:checked').val();
        $('[data-unit-label]').text(type === 'fixed' ? L.amount : L.percent);
        $('#cp-value').attr('max', type === 'percentage' ? 100 : null);
    }
    $(formEl).on('change', '[name="discount_type"]', unitLabel);
    unitLabel();

    $code.on('input', function () {
        var pos = this.selectionStart;
        this.value = this.value.toUpperCase().replace(/\s+/g, '');
        try { this.setSelectionRange(pos, pos); } catch (x) { /* number inputs */ }
        $('[data-w-title]').text(this.value || L.untitled);
    });
    $('#cp-generate').on('click', function () {
        var alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789', out = '';
        var bytes = new Uint32Array(8);
        window.crypto.getRandomValues(bytes);
        bytes.forEach(function (b) { out += alphabet[b % alphabet.length]; });
        $code.val(out).trigger('input');
        formEl.dispatchEvent(new Event('input', { bubbles: true }));
    });

    var form = WDForm.create({
        form: formEl,
        i18n: { saving: L.saving, fixErrors: L.fixErrors, errorsTitle: L.errorsTitle, errorTitleOne: L.errorTitleOne, failed: L.failed, leave: L.leave },
        validate: function (v) {
            var e = [];
            var code = $.trim(v.code || '');
            if (!code) { e.push({ field: 'code', message: L.errCode }); }
            else if (!/^[A-Z0-9_-]{3,50}$/.test(code)) { e.push({ field: 'code', message: L.errCodeChars }); }
            if (v.discount_type !== 'free') {
                var n = parseFloat(v.discount_value);
                if (!(n > 0)) { e.push({ field: 'discount_value', message: L.errValue }); }
                else if (v.discount_type === 'percentage' && n > 100) { e.push({ field: 'discount_value', message: L.errPercent }); }
            }
            return e;
        },
        submit: function (fd) {
            fd.append('action', 'sc_save_coupon');
            fd.append('nonce', scDashboard.nonce);
            return fetch(scDashboard.ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); });
        },
        onSuccess: function (data, api) {
            api.markClean();
            if (!isEdit && data.redirect) { window.location.href = data.redirect; return; }
            if (window.toastr) { toastr.success(data.message || L.saved); }
        }
    });

    if (/[?&]created=1/.test(location.search)) {
        if (window.toastr) { toastr.success(L.created); }
        history.replaceState(null, '', location.pathname + location.search.replace(/[?&]created=1/, '').replace(/^&/, '?'));
    }

    $('#delete-coupon').on('click', function () {
        showDeleteConfirm(L.confirmDelete.replace('%s', $code.val())).then(function (r) {
            if (!r.isConfirmed) { return; }
            $.ajax({ url: scDashboard.ajaxurl, type: 'POST', data: { action: 'delete_coupon', nonce: scDashboard.nonce, coupon_id: couponId } })
                .done(function (res) {
                    if (res.success) { form.markClean(); window.location.href = dashboardUrl + 'coupons'; }
                    else { showError(res.data && res.data.message ? res.data.message : L.failed); }
                })
                .fail(function () { showError(L.failed); });
        });
    });
});
</script>
