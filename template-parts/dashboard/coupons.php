<?php
/**
 * Coupons — list pattern over sc_get_coupons_paginated.
 *
 * Codes are sc_coupon posts; the tab counts, rows and CSV export share the
 * filters in inc/admin-dashboard/coupons-query.php.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

global $wpdb, $load_wd_list;
$load_wd_list = true;

$events = $wpdb->get_results("SELECT id, title FROM {$wpdb->prefix}sc_events WHERE status IN ('publish', 'completed', 'draft') ORDER BY start_date DESC LIMIT 200");
$categories = SC_Coupon_Category::get_all();
$dashboard_url = home_url('/event-manager-dashboard/');
$currency = get_option('sc_currency_code', 'EGP');
$js = function ($value) {
    return wp_json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
};
$t = array(
    'title'      => sc_t('dashboard_pages.coupons_management', 'Coupons'),
    'subtitle'   => sc_t('dashboard_pages.coupons_subtitle', 'Codes people enter when they register. Each code works until it reaches its usage limit or expiry date.'),
    'search'     => sc_t('dashboard_pages.search_code', 'Search code'),
    'all_events' => sc_t('dashboard_pages.all_events', 'All events'),
    'every_event'=> sc_t('dashboard_pages.valid_every_event', 'Valid for every event'),
    'all_cats'   => sc_t('dashboard_pages.all_categories', 'All categories'),
);

get_template_part('template-parts/dashboard/components/dashboard', 'header');
get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');
?>

<div id="main-content">
<div class="container-fluid">

    <div class="w-page-head">
        <div>
            <h1><?php echo esc_html($t['title']); ?><span class="w-page-head__count" data-w-total></span></h1>
            <p class="w-page-head__sub"><?php echo esc_html($t['subtitle']); ?></p>
        </div>
        <div class="w-page-head__actions">
            <a class="btn btn-secondary" href="<?php echo esc_url($dashboard_url . 'coupon-categories'); ?>"><?php echo esc_html(sc_t('dashboard_pages.coupon_categories', 'Categories')); ?></a>
            <button type="button" class="btn btn-secondary" data-toggle="modal" data-target="#importModal"><i class="fa fa-upload" aria-hidden="true"></i> <?php echo esc_html(sc_t('dashboard_pages.import', 'Import')); ?></button>
            <button type="button" class="btn btn-secondary" id="export-btn"><i class="fa fa-download" aria-hidden="true"></i> <?php echo esc_html(sc_t('dashboard_pages.export', 'Export')); ?></button>
            <button type="button" class="btn btn-secondary" id="recount-btn"><i class="fa fa-refresh" aria-hidden="true"></i> <?php echo esc_html(sc_t('dashboard_pages.recount_uses', 'Recount uses')); ?></button>
            <a class="btn btn-secondary" href="<?php echo esc_url($dashboard_url . 'coupon-create?mode=generate'); ?>"><?php echo esc_html(sc_t('dashboard_pages.generate_codes', 'Generate codes')); ?></a>
            <a class="btn btn-primary" href="<?php echo esc_url($dashboard_url . 'coupon-create'); ?>"><i class="fa fa-plus" aria-hidden="true"></i> <?php echo esc_html(sc_t('dashboard_pages.new_coupon', 'New coupon')); ?></a>
        </div>
    </div>

    <div id="coupons-list">
        <div class="w-tabs" role="tablist" data-w-tabs aria-label="<?php echo esc_attr($t['title']); ?>"></div>

        <div class="w-toolbar">
            <label class="w-search">
                <span class="sr-only"><?php echo esc_html($t['search']); ?></span>
                <svg class="w-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true"><path d="M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14zM20 20l-3.5-3.5"/></svg>
                <input type="search" class="form-control w-ltr" data-w-filter="search" placeholder="<?php echo esc_attr($t['search']); ?>" autocomplete="off" spellcheck="false">
                <kbd class="w-search__kbd" aria-hidden="true">/</kbd>
            </label>
            <select class="form-control" data-w-filter="event_id" aria-label="<?php echo esc_attr($t['all_events']); ?>">
                <option value=""><?php echo esc_html($t['all_events']); ?></option>
                <option value="none"><?php echo esc_html($t['every_event']); ?></option>
                <?php foreach ($events as $ev): ?>
                    <option value="<?php echo (int) $ev->id; ?>"><?php echo esc_html($ev->title); ?></option>
                <?php endforeach; ?>
            </select>
            <select class="form-control" data-w-filter="category_id" aria-label="<?php echo esc_attr($t['all_cats']); ?>">
                <option value=""><?php echo esc_html($t['all_cats']); ?></option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo (int) $cat->id; ?>"><?php echo esc_html($cat->name); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="w-chips" data-w-chips hidden></div>
        <div class="w-bulkbar" data-w-bulk hidden></div>

        <div class="w-table-card" data-w-card aria-live="polite">
            <div class="w-table-card__progress" data-w-progress hidden></div>
            <div class="w-table-scroll" data-w-scroll>
                <table class="w-table" data-w-table>
                    <thead></thead>
                    <tbody></tbody>
                </table>
            </div>
            <div class="w-state" data-w-state hidden></div>
            <div class="w-pager" data-w-pager hidden></div>
        </div>
    </div>

</div>
</div>

<div class="modal fade" id="categoryModal" tabindex="-1" role="dialog" aria-labelledby="category-title">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="category-title"><?php echo esc_html(sc_t('dashboard_pages.move_to_category', 'Move to category')); ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="<?php echo esc_attr(sc_t('dashboard_pages.close', 'Close')); ?>"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <label for="move-category" class="w-field__label"><?php echo esc_html(sc_t('dashboard_pages.category', 'Category')); ?></label>
                <select class="form-control" id="move-category">
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo (int) $cat->id; ?>"><?php echo esc_html($cat->name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo esc_html(sc_t('dashboard_pages.cancel', 'Cancel')); ?></button>
                <button type="button" class="btn btn-primary" id="move-category-go"><?php echo esc_html(sc_t('dashboard_pages.move', 'Move')); ?></button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="importModal" tabindex="-1" role="dialog" aria-labelledby="import-title">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="import-form" novalidate>
            <div class="modal-header">
                <h5 class="modal-title" id="import-title"><?php echo esc_html(sc_t('dashboard_pages.import_coupons', 'Import coupons')); ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="<?php echo esc_attr(sc_t('dashboard_pages.close', 'Close')); ?>"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="w-fields">
                    <div class="w-field">
                        <label for="import-file"><?php echo esc_html(sc_t('dashboard_pages.csv_file', 'CSV file')); ?></label>
                        <input type="file" class="form-control" id="import-file" accept=".csv,text/csv">
                        <p class="w-field__help"><?php echo esc_html(sc_t('dashboard_pages.coupon_csv_help', 'Columns: code, discount_type (free, percentage or fixed), discount_value, usage_limit, expiry_date (YYYY-MM-DD). Only the code is required — the rest default to free, single use, no expiry. Codes that already exist are skipped.')); ?></p>
                        <button type="button" class="btn btn-link p-0" id="template-btn"><?php echo esc_html(sc_t('dashboard_pages.download_template', 'Download a template')); ?></button>
                    </div>
                    <div class="w-field">
                        <label for="import-event"><?php echo esc_html(sc_t('dashboard_pages.valid_for', 'Valid for')); ?></label>
                        <select class="form-control" id="import-event">
                            <option value="0"><?php echo esc_html($t['every_event']); ?></option>
                            <?php foreach ($events as $ev): ?>
                                <option value="<?php echo (int) $ev->id; ?>"><?php echo esc_html($ev->title); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="w-field">
                        <label for="import-category"><?php echo esc_html(sc_t('dashboard_pages.category', 'Category')); ?></label>
                        <select class="form-control" id="import-category">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo (int) $cat->id; ?>"><?php echo esc_html($cat->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo esc_html(sc_t('dashboard_pages.cancel', 'Cancel')); ?></button>
                <button type="submit" class="btn btn-primary" id="import-go"><?php echo esc_html(sc_t('dashboard_pages.import', 'Import')); ?></button>
            </div>
            </form>
        </div>
    </div>
</div>

<script>
jQuery(function ($) {
    'use strict';

    var esc = WDList.esc;
    var dashboardUrl = <?php echo $js($dashboard_url); ?>;
    var currency = <?php echo $js($currency); ?>;
    var eventTitles = <?php echo $js(array_reduce($events, function ($c, $e) { $c[(int) $e->id] = $e->title; return $c; }, array())); ?>;
    var categoryNames = <?php echo $js(array_reduce($categories, function ($c, $k) { $c[(int) $k->id] = $k->name; return $c; }, array())); ?>;
    var L = <?php echo $js(array(
        'all'          => sc_t('dashboard_pages.all', 'All'),
        'unused'       => sc_t('dashboard_pages.unused', 'Unused'),
        'used'         => sc_t('dashboard_pages.used', 'Used'),
        'full'         => sc_t('dashboard_pages.limit_reached', 'Limit reached'),
        'expired'      => sc_t('dashboard_pages.expired', 'Expired'),
        'inactive'     => sc_t('dashboard_pages.inactive', 'Inactive'),
        'active'       => sc_t('dashboard_pages.active', 'Active'),
        'code'         => sc_t('dashboard_pages.code', 'Code'),
        'discount'     => sc_t('dashboard_pages.discount', 'Discount'),
        'validFor'     => sc_t('dashboard_pages.valid_for', 'Valid for'),
        'everyEvent'   => sc_t('dashboard_pages.every_event', 'Every event'),
        'category'     => sc_t('dashboard_pages.category', 'Category'),
        'uses'         => sc_t('dashboard_pages.uses', 'Uses'),
        'usedBy'       => sc_t('dashboard_pages.used_by', 'Used by'),
        'expires'      => sc_t('dashboard_pages.expires', 'Expires'),
        'status'       => sc_t('dashboard_pages.status', 'Status'),
        'free'         => sc_t('dashboard_pages.free_100', 'Free (100%)'),
        'off'          => sc_t('dashboard_pages.n_off', '%s off'),
        'unlimited'    => sc_t('dashboard_pages.unlimited', 'Unlimited'),
        'general'      => sc_t('dashboard_pages.general_tickets_only', 'General tickets only'),
        'competitor'   => sc_t('dashboard_pages.competitor_tickets_only', 'Competitor tickets only'),
        'more'         => sc_t('dashboard_pages.n_more', '+%d more'),
        'edit'         => sc_t('dashboard_pages.edit', 'Edit'),
        'copy'         => sc_t('dashboard_pages.copy_code', 'Copy code'),
        'copied'       => sc_t('dashboard_pages.copied', 'Copied %s'),
        'registrations'=> sc_t('dashboard_pages.see_registrations', 'See registrations'),
        'activate'     => sc_t('dashboard_pages.activate', 'Activate'),
        'deactivate'   => sc_t('dashboard_pages.deactivate', 'Deactivate'),
        'move'         => sc_t('dashboard_pages.move_to_category', 'Move to category'),
        'delete'       => sc_t('dashboard_pages.delete', 'Delete'),
        'search'       => sc_t('general.search', 'Search'),
        'event'        => sc_t('events.event', 'Event'),
        'emptyText'    => sc_t('dashboard_pages.no_coupons', 'No coupons yet. Create one, generate a batch or import a CSV.'),
        'confirmDelete'=> sc_t('dashboard_pages.confirm_delete_coupons', 'Delete %d coupons? Anyone holding these codes can no longer register with them. People who already registered keep their registration. This cannot be undone.'),
        'confirmDeleteOne' => sc_t('dashboard_pages.confirm_delete_coupon', 'Delete %s? Anyone holding this code can no longer register with it. This cannot be undone.'),
        'confirmDeactivate' => sc_t('dashboard_pages.confirm_deactivate_coupons', 'Deactivate %d coupons? They stop working at registration until you activate them again.'),
        'confirmExport'=> sc_t('dashboard_pages.confirm_export_coupons', 'Export %s coupons matching the current view to CSV?'),
        'confirmRecount' => sc_t('dashboard_pages.confirm_recount', 'Recount uses from registrations? Each coupon’s use count is set to the number of active registrations that hold its code. Codes whose registrations were cancelled become usable again; codes shared by several people are marked as used up.'),
        'chooseFile'   => sc_t('dashboard_pages.choose_csv', 'Choose a CSV file first.'),
        'importing'    => sc_t('dashboard_pages.importing', 'Importing…'),
        'working'      => sc_t('dashboard_pages.working', 'Working…'),
        'failed'       => sc_t('errors.something_wrong', 'Something went wrong. Please try again.'),
    )); ?>;

    function post(data) {
        return $.ajax({ url: scDashboard.ajaxurl, type: 'POST', data: $.extend({ nonce: scDashboard.nonce }, data) });
    }
    function bulk(op, ids, extra) {
        return post($.extend({ action: 'sc_bulk_coupons', op: op, ids: ids }, extra || {}))
            .done(function (res) {
                if (res.success) { showSuccess(res.data.message); list.reload(); }
                else { showError(res.data && res.data.message ? res.data.message : L.failed); }
            })
            .fail(function () { showError(L.failed); });
    }
    function confirmDelete(ids, code) {
        showDeleteConfirm(code ? L.confirmDeleteOne.replace('%s', code) : L.confirmDelete.replace('%d', ids.length)).then(function (r) {
            if (r.isConfirmed) { bulk('delete', ids); }
        });
    }
    function money(n) { return Number(n).toLocaleString('en-US', { maximumFractionDigits: 2 }) + ' ' + currency; }
    function discount(c) {
        if (c.discount_type === 'percentage') { return c.discount_value >= 100 ? L.free : L.off.replace('%s', Number(c.discount_value) + '%'); }
        return L.off.replace('%s', money(c.discount_value));
    }
    function copy(code) {
        var done = function () { showSuccess(L.copied.replace('%s', code)); };
        if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(code).then(done, function () { fallbackCopy(code); done(); }); }
        else { fallbackCopy(code); done(); }
    }
    function fallbackCopy(text) {
        var ta = document.createElement('textarea');
        ta.value = text; ta.setAttribute('readonly', ''); ta.style.position = 'fixed'; ta.style.opacity = '0';
        document.body.appendChild(ta); ta.select();
        try { document.execCommand('copy'); } catch (e) { /* nothing else to try */ }
        document.body.removeChild(ta);
    }

    var ICON = {
        pencil: 'M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z',
        copy: 'M9 9h11v11H9zM5 15H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v1',
        users: 'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM23 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8',
        on: 'M20 6 9 17l-5-5',
        off: 'M18.4 5.6 5.6 18.4M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20z',
        folder: 'M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z',
        trash: 'M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14'
    };
    var STATE_TAG = {
        active: '<span class="w-tag w-tag--teal">' + esc(L.active) + '</span>',
        inactive: '<span class="w-tag">' + esc(L.inactive) + '</span>',
        expired: '<span class="w-tag w-tag--gold">' + esc(L.expired) + '</span>',
        full: '<span class="w-tag w-tag--primary">' + esc(L.full) + '</span>'
    };

    var list = WDList.create({
        root: document.getElementById('coupons-list'),
        action: 'sc_get_coupons_paginated',
        rowsKey: 'rows',
        filters: ['search', 'event_id', 'category_id'],
        perPage: 50,
        perPageOptions: [50, 100, 200],
        defaultSort: { orderby: 'created', order: 'desc' },
        tabs: [
            { key: 'all', label: L.all, params: { view: 'all' }, countKey: 'all' },
            { key: 'unused', label: L.unused, params: { view: 'unused' }, countKey: 'unused' },
            { key: 'used', label: L.used, params: { view: 'used' }, countKey: 'used' },
            { key: 'full', label: L.full, params: { view: 'full' }, countKey: 'full' },
            { key: 'expired', label: L.expired, params: { view: 'expired' }, countKey: 'expired' },
            { key: 'inactive', label: L.inactive, params: { view: 'inactive' }, countKey: 'inactive' }
        ],
        emptyText: L.emptyText,
        columns: [
            {
                label: L.code, sort: 'code',
                render: function (c) {
                    return '<div class="w-stack"><span class="w-codecell"><a class="w-row-title w-mono w-ltr" href="' + esc(dashboardUrl + 'coupon-edit?id=' + c.id) + '">' + esc(c.code) + '</a> ' +
                        '<button type="button" class="w-copy" data-copy="' + esc(c.code) + '" aria-label="' + esc(L.copy + ' ' + c.code) + '" title="' + esc(L.copy) + '">' + WDList.icon(ICON.copy, 14) + '</button></span>' +
                        (c.note ? '<span class="w-sub w-truncate">' + esc(c.note) + '</span>' : '') + '</div>';
                }
            },
            {
                label: L.discount,
                render: function (c) { return '<span class="w-nowrap">' + esc(discount(c)) + '</span>'; }
            },
            {
                label: L.validFor,
                render: function (c) {
                    var main = c.event_id ? '<span class="w-truncate">' + esc(c.event_title || ('#' + c.event_id)) + '</span>' : '<span class="text-muted">' + esc(L.everyEvent) + '</span>';
                    return '<div class="w-stack">' + main + (c.ticket_filter !== 'all' ? '<span class="w-sub">' + esc(L[c.ticket_filter]) + '</span>' : '') + '</div>';
                }
            },
            {
                label: L.category, className: 'w-col-xl',
                render: function (c) {
                    return '<span class="w-nowrap"><span class="w-swatch" style="background:' + esc(c.category_color) + '" aria-hidden="true"></span>' + esc(c.category) + '</span>';
                }
            },
            {
                label: L.uses, sort: 'uses',
                render: function (c) {
                    if (!c.usage_limit) { return '<div class="w-stack"><span class="w-num">' + WDList.num(c.usage_count) + '</span><span class="w-sub">' + esc(L.unlimited) + '</span></div>'; }
                    var pct = Math.min(100, Math.round(c.usage_count / c.usage_limit * 100));
                    return '<div class="w-stack"><span class="w-num w-nowrap">' + WDList.num(c.usage_count) + ' / ' + WDList.num(c.usage_limit) + '</span>' +
                        (c.usage_limit > 1 ? '<span class="w-bar" role="img" aria-label="' + pct + '%"><span class="w-bar__fill' + (pct >= 100 ? ' is-full' : '') + '" style="width:' + pct + '%"></span></span>' : '') + '</div>';
                }
            },
            {
                label: L.usedBy,
                render: function (c) {
                    if (!c.used_by_total) { return '<span class="text-muted">—</span>'; }
                    var names = c.used_by.map(function (a) { return '<a href="' + esc(dashboardUrl + 'attendee-edit?id=' + a.id) + '" class="w-truncate">' + esc(a.name) + '</a>'; }).join('');
                    var more = c.used_by_total > c.used_by.length
                        ? '<a class="w-sub" href="' + esc(dashboardUrl + 'attendees?coupon_code=' + encodeURIComponent(c.code)) + '">' + esc(L.more.replace('%d', c.used_by_total - c.used_by.length)) + '</a>' : '';
                    return '<div class="w-stack">' + names + more + '</div>';
                }
            },
            {
                label: L.expires, className: 'w-col-xl',
                render: function (c) { return c.expiry_date ? '<span class="w-nowrap">' + esc(new Date(c.expiry_date + 'T00:00:00').toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })) + '</span>' : '<span class="text-muted">—</span>'; }
            },
            {
                label: L.status,
                render: function (c) { return STATE_TAG[c.state] || ''; }
            }
        ],
        rowMenu: function (c) {
            return [
                { label: L.edit, icon: ICON.pencil, href: dashboardUrl + 'coupon-edit?id=' + c.id },
                { label: L.copy, icon: ICON.copy, onSelect: function () { copy(c.code); } },
                { label: L.registrations, icon: ICON.users, href: dashboardUrl + 'attendees?coupon_code=' + encodeURIComponent(c.code), disabled: !c.used_by_total },
                c.state === 'inactive'
                    ? { label: L.activate, icon: ICON.on, onSelect: function () { bulk('activate', [c.id]); } }
                    : { label: L.deactivate, icon: ICON.off, onSelect: function () { bulk('deactivate', [c.id]); } },
                { separator: true },
                { label: L.delete, icon: ICON.trash, danger: true, onSelect: function () { confirmDelete([c.id], c.code); } }
            ];
        },
        bulkActions: [
            { key: 'activate', label: L.activate, icon: ICON.on, run: function (ids) { bulk('activate', ids); } },
            {
                key: 'deactivate', label: L.deactivate, icon: ICON.off,
                run: function (ids) { showConfirm(L.confirmDeactivate.replace('%d', ids.length)).then(function (r) { if (r.isConfirmed) { bulk('deactivate', ids); } }); }
            },
            { key: 'category', label: L.move, icon: ICON.folder, run: function (ids) { moveIds = ids; $('#categoryModal').modal('show'); } },
            { key: 'delete', label: L.delete, icon: ICON.trash, danger: true, run: function (ids) { confirmDelete(ids); } }
        ],
        chips: function (state) {
            var f = state.filters, chips = [];
            if (f.search) { chips.push({ label: L.search, value: f.search, clear: function (l) { l.setFilter('search', ''); } }); }
            if (f.event_id) { chips.push({ label: L.validFor, value: f.event_id === 'none' ? L.everyEvent : (eventTitles[f.event_id] || ('#' + f.event_id)), clear: function (l) { l.setFilter('event_id', ''); } }); }
            if (f.category_id) { chips.push({ label: L.category, value: categoryNames[f.category_id] || ('#' + f.category_id), clear: function (l) { l.setFilter('category_id', ''); } }); }
            return chips;
        }
    });

    $('#coupons-list').on('click', '[data-copy]', function (e) { e.preventDefault(); copy(this.getAttribute('data-copy')); });

    var moveIds = [];
    $('#move-category-go').on('click', function () {
        var $b = $(this).prop('disabled', true);
        bulk('category', moveIds, { category_id: $('#move-category').val() }).always(function () { $b.prop('disabled', false); $('#categoryModal').modal('hide'); });
    });

    $('#export-btn').on('click', function () {
        var data = list.data();
        showConfirm(L.confirmExport.replace('%s', WDList.num(data ? data.total : 0))).then(function (r) {
            if (!r.isConfirmed) { return; }
            var p = list.params();
            p.action = 'sc_export_coupons_csv';
            p.nonce = scDashboard.nonce;
            window.location.href = scDashboard.ajaxurl + '?' + $.param(p);
        });
    });

    $('#recount-btn').on('click', function () {
        showConfirm(L.confirmRecount).then(function (r) {
            if (!r.isConfirmed) { return; }
            var $b = $('#recount-btn').prop('disabled', true);
            post({ action: 'sc_sync_coupon_usage' }).done(function (res) {
                if (res.success) { showSuccess(res.data.message); list.reload(); } else { showError(res.data && res.data.message || L.failed); }
            }).fail(function () { showError(L.failed); }).always(function () { $b.prop('disabled', false); });
        });
    });

    $('#template-btn').on('click', function () {
        var csv = 'code,discount_type,discount_value,usage_limit,expiry_date\r\nWELCOME2026,free,,1,\r\nHALFPRICE,percentage,50,100,2026-12-31\r\n';
        var a = document.createElement('a');
        a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }));
        a.download = 'coupons-template.csv';
        document.body.appendChild(a); a.click(); document.body.removeChild(a);
    });

    $('#import-form').on('submit', function (e) {
        e.preventDefault();
        var file = document.getElementById('import-file').files[0];
        if (!file) { showError(L.chooseFile); return; }
        var $b = $('#import-go').prop('disabled', true).text(L.importing);
        var reader = new FileReader();
        reader.onload = function () {
            post({ action: 'import_coupons', csv_data: String(reader.result).replace(/^﻿/, ''), event_id: $('#import-event').val(), category_id: $('#import-category').val() })
                .done(function (res) {
                    if (res.success) { $('#importModal').modal('hide'); showSuccess(res.data.message); list.reload(); document.getElementById('import-form').reset(); }
                    else { showError(res.data && res.data.message || L.failed); }
                })
                .fail(function () { showError(L.failed); })
                .always(function () { $b.prop('disabled', false).text(<?php echo $js(sc_t('dashboard_pages.import', 'Import')); ?>); });
        };
        reader.onerror = function () { $b.prop('disabled', false); showError(L.failed); };
        reader.readAsText(file);
    });
});
</script>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
