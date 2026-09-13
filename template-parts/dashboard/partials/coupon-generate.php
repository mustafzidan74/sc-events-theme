<?php
/**
 * Generate coupon codes — a batch of random codes with the same settings.
 * Posts to sc_bulk_create_coupons; the codes come back for CSV download.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$preselect_event = isset($_GET['event_id']) ? absint($_GET['event_id']) : 0;
$events = $wpdb->get_results("SELECT id, title, start_date FROM {$wpdb->prefix}sc_events WHERE status IN ('publish', 'completed', 'draft') ORDER BY start_date DESC LIMIT 200");
$categories = SC_Coupon_Category::get_all();
$dashboard_url = home_url('/event-manager-dashboard/');
$currency = get_option('sc_currency_code', 'EGP');
$js = function ($value) {
    return wp_json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
};
?>

<div id="main-content">
<div class="container-fluid">
<form id="generate-form" novalidate autocomplete="off">

    <div class="w-form-head">
        <div>
            <span class="w-form-head__eyebrow"><?php echo esc_html(sc_t('dashboard_pages.coupons_management', 'Coupons')); ?></span>
            <h1><?php echo esc_html(sc_t('dashboard_pages.generate_codes', 'Generate codes')); ?></h1>
            <div class="w-form-head__meta"><span class="w-sub"><?php echo esc_html(sc_t('dashboard_pages.generate_codes_sub', 'Random codes with the same settings — for invitations, sponsors or printed cards.')); ?></span></div>
        </div>
        <div class="w-form-head__actions">
            <a class="btn btn-secondary" href="<?php echo esc_url($dashboard_url . 'coupons'); ?>"><?php echo esc_html(sc_t('dashboard_pages.cancel', 'Cancel')); ?></a>
            <a class="btn btn-secondary" href="<?php echo esc_url($dashboard_url . 'coupon-create'); ?>"><?php echo esc_html(sc_t('dashboard_pages.single_coupon', 'One coupon instead')); ?></a>
            <button type="submit" class="btn btn-primary w-save-head" data-w-save><?php echo esc_html(sc_t('dashboard_pages.generate', 'Generate')); ?></button>
        </div>
    </div>

    <div class="w-errors" data-w-errors hidden></div>

    <section class="w-section w-result" id="result" hidden aria-live="polite">
        <div class="w-section__head"><h2 id="result-title"></h2></div>
        <p class="w-field__help" id="result-help"></p>
        <div class="w-aside-actions">
            <button type="button" class="btn btn-primary" id="download-csv"><i class="fa fa-download" aria-hidden="true"></i> <?php echo esc_html(sc_t('dashboard_pages.download_csv', 'Download CSV')); ?></button>
            <button type="button" class="btn btn-secondary" id="copy-all"><?php echo esc_html(sc_t('dashboard_pages.copy_all', 'Copy all codes')); ?></button>
            <a class="btn btn-secondary" id="see-in-list" href="<?php echo esc_url($dashboard_url . 'coupons'); ?>"><?php echo esc_html(sc_t('dashboard_pages.see_in_list', 'See them in the list')); ?></a>
            <button type="button" class="btn btn-link" id="generate-more"><?php echo esc_html(sc_t('dashboard_pages.generate_more', 'Generate more')); ?></button>
        </div>
        <pre class="w-codes w-mono w-ltr" id="result-codes"></pre>
    </section>

    <div class="w-form-layout w-form-layout--noseq" id="generate-body">
        <div class="w-form-main">
            <section class="w-section" aria-labelledby="g-codes-h">
                <div class="w-section__head"><h2 id="g-codes-h"><?php echo esc_html(sc_t('dashboard_pages.codes', 'Codes')); ?></h2></div>
                <div class="w-fields">
                    <div class="w-fields w-fields--3">
                        <div class="w-field">
                            <label for="g-count"><?php echo esc_html(sc_t('dashboard_pages.how_many', 'How many')); ?><span class="w-req" aria-hidden="true">*</span></label>
                            <input type="number" class="form-control" id="g-count" name="count" value="100" min="1" max="10000" step="1" inputmode="numeric" required>
                        </div>
                        <div class="w-field">
                            <label for="g-prefix"><?php echo esc_html(sc_t('dashboard_pages.prefix', 'Prefix')); ?></label>
                            <input type="text" class="form-control w-ltr w-mono" id="g-prefix" name="prefix" maxlength="20" placeholder="IDC26-" spellcheck="false" autocapitalize="characters">
                        </div>
                        <div class="w-field">
                            <label for="g-length"><?php echo esc_html(sc_t('dashboard_pages.random_characters', 'Random characters')); ?></label>
                            <input type="number" class="form-control" id="g-length" name="code_length" value="8" min="4" max="20" step="1" inputmode="numeric">
                        </div>
                    </div>
                    <p class="w-field__help" id="g-preview"></p>
                    <p class="w-field__help"><?php echo esc_html(sc_t('dashboard_pages.generate_alphabet_help', 'Codes skip letters and digits that are easy to confuse (O/0, I/1). A prefix makes a batch easy to find later.')); ?></p>
                </div>
            </section>

            <section class="w-section" aria-labelledby="g-discount-h">
                <div class="w-section__head"><h2 id="g-discount-h"><?php echo esc_html(sc_t('dashboard_pages.discount', 'Discount')); ?></h2></div>
                <div class="w-fields">
                    <div class="w-choice" role="radiogroup" aria-labelledby="g-discount-h">
                        <?php foreach (array(
                            'free'       => array(sc_t('dashboard_pages.free_ticket', 'Free ticket'), sc_t('dashboard_pages.free_ticket_help', '100% off')),
                            'percentage' => array(sc_t('dashboard_pages.percentage', 'Percentage'), sc_t('dashboard_pages.percentage_help', 'e.g. 25% off')),
                            'fixed'      => array(sc_t('dashboard_pages.fixed_amount', 'Fixed amount'), sprintf(sc_t('dashboard_pages.fixed_amount_help', '%s off the price'), $currency)),
                        ) as $value => $labels): ?>
                        <label class="w-choice__item"><input type="radio" name="discount_type" value="<?php echo esc_attr($value); ?>" <?php checked($value, 'free'); ?>><span class="w-choice__box"><span><?php echo esc_html($labels[0]); ?><span class="w-choice__sub"><?php echo esc_html($labels[1]); ?></span></span></span></label>
                        <?php endforeach; ?>
                    </div>
                    <div class="w-field" data-w-show-if="discount_type:percentage,fixed">
                        <label for="g-value"><span data-unit-label></span><span class="w-req" aria-hidden="true">*</span></label>
                        <input type="number" class="form-control" id="g-value" name="discount_value" min="0" step="0.01" inputmode="decimal">
                    </div>
                </div>
            </section>

            <section class="w-section" aria-labelledby="g-where-h">
                <div class="w-section__head"><h2 id="g-where-h"><?php echo esc_html(sc_t('dashboard_pages.where_and_limits', 'Where and how often')); ?></h2></div>
                <div class="w-fields w-fields--2">
                    <div class="w-field">
                        <label for="g-event"><?php echo esc_html(sc_t('events.event', 'Event')); ?></label>
                        <select class="form-control" id="g-event" name="event_id">
                            <option value="0"><?php echo esc_html(sc_t('dashboard_pages.valid_every_event', 'Valid for every event')); ?></option>
                            <?php foreach ($events as $ev): ?>
                                <option value="<?php echo (int) $ev->id; ?>" <?php selected($preselect_event, (int) $ev->id); ?>><?php echo esc_html($ev->title); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="w-field">
                        <label for="g-ticket"><?php echo esc_html(sc_t('dashboard_pages.ticket_type', 'Ticket type')); ?></label>
                        <select class="form-control" id="g-ticket" name="ticket_type_filter">
                            <option value="all"><?php echo esc_html(sc_t('dashboard_pages.all_tickets', 'All tickets')); ?></option>
                            <option value="general"><?php echo esc_html(sc_t('dashboard_pages.general_tickets_only', 'General tickets only')); ?></option>
                            <option value="competitor"><?php echo esc_html(sc_t('dashboard_pages.competitor_tickets_only', 'Competitor tickets only')); ?></option>
                        </select>
                    </div>
                    <div class="w-field">
                        <label for="g-limit"><?php echo esc_html(sc_t('dashboard_pages.uses_per_code', 'Uses per code')); ?></label>
                        <input type="number" class="form-control" id="g-limit" name="usage_limit" value="1" min="0" step="1" inputmode="numeric" placeholder="<?php echo esc_attr(sc_t('dashboard_pages.unlimited', 'Unlimited')); ?>">
                        <p class="w-field__help"><?php echo esc_html(sc_t('dashboard_pages.uses_per_code_help', '1 = each code registers one person. Leave empty for unlimited.')); ?></p>
                    </div>
                    <div class="w-field">
                        <label for="g-expiry"><?php echo esc_html(sc_t('dashboard_pages.expires', 'Expires')); ?></label>
                        <input type="date" class="form-control" id="g-expiry" name="expiry_date">
                        <p class="w-field__help"><?php echo esc_html(sc_t('dashboard_pages.expiry_help', 'Works through the end of this day. Leave empty to never expire.')); ?></p>
                    </div>
                    <div class="w-field">
                        <label for="g-category"><?php echo esc_html(sc_t('dashboard_pages.category', 'Category')); ?></label>
                        <select class="form-control" id="g-category" name="category_id">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo (int) $cat->id; ?>" <?php selected((int) $cat->id, SC_Coupon_Category::DEFAULT_ID); ?>><?php echo esc_html($cat->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </section>
        </div>
        <aside class="w-form-aside">
            <div class="w-aside-card">
                <span class="w-aside-card__title"><?php echo esc_html(sc_t('dashboard_pages.summary', 'Summary')); ?></span>
                <p class="mb-0" id="g-summary"></p>
            </div>
        </aside>
    </div>

    <div class="w-savebar">
        <button type="submit" class="btn btn-primary" data-w-save><?php echo esc_html(sc_t('dashboard_pages.generate', 'Generate')); ?></button>
    </div>
</form>
</div>
</div>

<script>
jQuery(function ($) {
    'use strict';

    var dashboardUrl = <?php echo $js($dashboard_url); ?>;
    var currency = <?php echo $js($currency); ?>;
    var L = <?php echo $js(array(
        'percent'     => sc_t('dashboard_pages.percent_off', 'Percent off'),
        'amount'      => sprintf(sc_t('dashboard_pages.amount_off', 'Amount off (%s)'), $currency),
        'example'     => sc_t('dashboard_pages.codes_look_like', 'Codes will look like %s'),
        'summary'     => sc_t('dashboard_pages.generate_summary', '%1$s codes · %2$s · %3$s · %4$s'),
        'free'        => sc_t('dashboard_pages.free_ticket', 'Free ticket'),
        'off'         => sc_t('dashboard_pages.n_off', '%s off'),
        'oneUse'      => sc_t('dashboard_pages.one_use_each', 'one use each'),
        'nUses'       => sc_t('dashboard_pages.n_uses_each', '%s uses each'),
        'unlimited'   => sc_t('dashboard_pages.unlimited_uses', 'unlimited uses'),
        'everyEvent'  => sc_t('dashboard_pages.every_event', 'every event'),
        'done'        => sc_t('dashboard_pages.n_codes_generated', '%s codes generated'),
        'doneHelp'    => sc_t('dashboard_pages.codes_generated_help', 'They work now. Download the list before leaving — you can also export them later from the coupons list.'),
        'copied'      => sc_t('dashboard_pages.codes_copied', 'Codes copied.'),
        'errCount'    => sc_t('dashboard_pages.err_count', 'Generate between 1 and 10,000 codes at a time.'),
        'errLength'   => sc_t('dashboard_pages.err_length', 'Random part: 4–20 characters.'),
        'errPrefix'   => sc_t('dashboard_pages.err_prefix', 'Letters, numbers, dashes and underscores only.'),
        'errValue'    => sc_t('dashboard_pages.err_discount_value', 'Enter the discount.'),
        'errPercent'  => sc_t('dashboard_pages.err_percent', 'A percentage is between 1 and 100.'),
        'confirmBig'  => sc_t('dashboard_pages.confirm_generate_big', 'Generate %s codes? This can take a minute or two — keep this page open.'),
        'saving'      => sc_t('dashboard_pages.generating', 'Generating… keep this page open'),
        'failed'      => sc_t('errors.something_wrong', 'Something went wrong. Please try again.'),
        'fixErrors'   => sc_t('dashboard_pages.fix_n', 'Fix %d to save'),
        'errorsTitle' => sc_t('dashboard_pages.errors_title', '%d fields need attention before saving.'),
        'errorTitleOne' => sc_t('dashboard_pages.error_title_one', 'One field needs attention before saving.'),
        'leave'       => sc_t('dashboard_pages.unsaved_leave', 'You have unsaved changes.'),
    )); ?>;
    var formEl = document.getElementById('generate-form');
    var $f = $(formEl);
    var alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    var codes = [], lastSettings = {};

    function val(name) { return $f.find('[name="' + name + '"]').filter(function () { return this.type !== 'radio' || this.checked; }).val(); }
    function sample(prefix, length) {
        var out = prefix;
        for (var i = 0; i < length; i++) { out += alphabet[Math.floor(Math.random() * alphabet.length)]; }
        return out;
    }
    function refresh() {
        var type = val('discount_type');
        $('[data-unit-label]').text(type === 'fixed' ? L.amount : L.percent);
        var prefix = String(val('prefix') || '').toUpperCase();
        var length = Math.min(20, Math.max(4, parseInt(val('code_length'), 10) || 8));
        $('#g-preview').text(L.example.replace('%s', sample(prefix, length) + ', ' + sample(prefix, length)));
        var discount = type === 'free' ? L.free : L.off.replace('%s', type === 'percentage' ? (val('discount_value') || 0) + '%' : (val('discount_value') || 0) + ' ' + currency);
        var limit = parseInt(val('usage_limit'), 10) || 0;
        var event = +val('event_id') ? $('#g-event option:selected').text() : L.everyEvent;
        $('#g-summary').text(L.summary.replace('%1$s', Number(val('count') || 0).toLocaleString('en-US')).replace('%2$s', discount)
            .replace('%3$s', limit === 1 ? L.oneUse : limit ? L.nUses.replace('%s', limit) : L.unlimited).replace('%4$s', event));
    }
    $f.on('input change', refresh);
    $('#g-prefix').on('input', function () { this.value = this.value.toUpperCase().replace(/\s+/g, ''); });
    refresh();

    var form = WDForm.create({
        form: formEl,
        i18n: { save: <?php echo $js(sc_t('dashboard_pages.generate', 'Generate')); ?>, saving: L.saving, fixErrors: L.fixErrors, errorsTitle: L.errorsTitle, errorTitleOne: L.errorTitleOne, failed: L.failed, leave: L.leave },
        validate: function (v) {
            var e = [];
            var count = parseInt(v.count, 10);
            if (!(count >= 1 && count <= 10000)) { e.push({ field: 'count', message: L.errCount }); }
            var length = parseInt(v.code_length, 10);
            if (!(length >= 4 && length <= 20)) { e.push({ field: 'code_length', message: L.errLength }); }
            if (v.prefix && !/^[A-Z0-9_-]+$/.test(v.prefix)) { e.push({ field: 'prefix', message: L.errPrefix }); }
            if (v.discount_type !== 'free') {
                var n = parseFloat(v.discount_value);
                if (!(n > 0)) { e.push({ field: 'discount_value', message: L.errValue }); }
                else if (v.discount_type === 'percentage' && n > 100) { e.push({ field: 'discount_value', message: L.errPercent }); }
            }
            return e;
        },
        submit: function (fd) {
            var count = parseInt(fd.get('count'), 10);
            var go = count > 1000 ? showConfirm(L.confirmBig.replace('%s', count.toLocaleString('en-US'))) : Promise.resolve({ isConfirmed: true });
            return go.then(function (r) {
                if (!r.isConfirmed) { return { success: false, data: { message: '' }, cancelled: true }; }
                lastSettings = { prefix: fd.get('prefix'), event_id: fd.get('event_id'), category_id: fd.get('category_id') };
                fd.append('action', 'sc_bulk_create_coupons');
                fd.append('nonce', scDashboard.nonce);
                return fetch(scDashboard.ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (res) { return res.json(); });
            });
        },
        onSuccess: function (data, api) {
            api.markClean();
            codes = data.codes || [];
            $('#result-title').text(L.done.replace('%s', Number(data.created).toLocaleString('en-US')));
            $('#result-help').text(L.doneHelp);
            $('#result-codes').text(codes.slice(0, 200).join('\n') + (codes.length > 200 ? '\n…' : ''));
            var q = { view: 'unused' };
            if (lastSettings.prefix) { q.search = String(lastSettings.prefix).toUpperCase(); }
            if (+lastSettings.event_id) { q.event_id = lastSettings.event_id; }
            if (lastSettings.category_id) { q.category_id = lastSettings.category_id; }
            $('#see-in-list').attr('href', dashboardUrl + 'coupons?' + $.param(q));
            $('#generate-body, .w-savebar, .w-save-head').prop('hidden', true);
            $('#result').prop('hidden', false);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    });

    $('#download-csv').on('click', function () {
        var csv = 'code\r\n' + codes.join('\r\n') + '\r\n';
        var a = document.createElement('a');
        a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }));
        a.download = 'coupon-codes-' + new Date().toISOString().slice(0, 10) + '.csv';
        document.body.appendChild(a); a.click(); document.body.removeChild(a);
    });
    $('#copy-all').on('click', function () {
        var text = codes.join('\n');
        var done = function () { showSuccess(L.copied); };
        if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(text).then(done); }
        else {
            var ta = document.createElement('textarea'); ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
            document.body.appendChild(ta); ta.select(); try { document.execCommand('copy'); } catch (x) { /* ignore */ } document.body.removeChild(ta); done();
        }
    });
    $('#generate-more').on('click', function () {
        $('#result').prop('hidden', true);
        $('#generate-body, .w-savebar, .w-save-head').prop('hidden', false);
        refresh();
        form.markClean();
    });
});
</script>
