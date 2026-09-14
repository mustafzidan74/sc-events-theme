<?php
/**
 * Sponsors / partners list — shared by sponsors.php and partners.php.
 * Lists sc_get_{type}s_paginated (inc/admin-dashboard/brands-dashboard.php).
 *
 * Expects: $brand_type ('sponsor'|'partner').
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$is_sponsor = $brand_type === 'sponsor';
$events = $wpdb->get_results("SELECT id, title FROM {$wpdb->prefix}sc_events WHERE status IN ('publish', 'completed', 'draft') ORDER BY start_date DESC LIMIT 200");
$dashboard_url = home_url('/event-manager-dashboard/');
$js = function ($value) {
    return wp_json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
};
$tiers = array(
    'diamond'  => sc_t('frontend.tier_diamond', 'Diamond'),
    'platinum' => sc_t('frontend.tier_platinum', 'Platinum'),
    'gold'     => sc_t('frontend.tier_gold', 'Gold'),
    'silver'   => sc_t('frontend.tier_silver', 'Silver'),
    'bronze'   => sc_t('frontend.tier_bronze', 'Bronze'),
);
$t = $is_sponsor ? array(
    'title'    => sc_t('nav.sponsors', 'Sponsors'),
    'subtitle' => sc_t('dashboard_pages.sponsors_subtitle', 'Sponsor logos appear on the Sponsors page and on the events they’re linked to. Link them in the event form’s Sponsors section.'),
    'add'      => sc_t('dashboard_pages.add_sponsor', 'Add sponsor'),
) : array(
    'title'    => sc_t('nav.partners', 'Partners'),
    'subtitle' => sc_t('dashboard_pages.partners_subtitle', 'Partner logos appear on the Sponsors page. Link them to events in the event form’s Sponsors section.'),
    'add'      => sc_t('dashboard_pages.add_partner', 'Add partner'),
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
            <a class="btn btn-secondary" href="<?php echo esc_url(home_url('/sponsors/')); ?>" target="_blank" rel="noopener"><i class="fa fa-external-link" aria-hidden="true"></i> <?php echo esc_html(sc_t('dashboard_pages.view_on_site', 'View on site')); ?></a>
            <a class="btn btn-primary" href="<?php echo esc_url($dashboard_url . $brand_type . '-create'); ?>"><i class="fa fa-plus" aria-hidden="true"></i> <?php echo esc_html($t['add']); ?></a>
        </div>
    </div>

    <div id="brands-list">
        <div class="w-tabs" role="tablist" data-w-tabs aria-label="<?php echo esc_attr($t['title']); ?>"></div>
        <div class="w-toolbar">
            <label class="w-search">
                <span class="sr-only"><?php echo esc_html(sc_t('dashboard_pages.search_brands', 'Search name, email or website')); ?></span>
                <svg class="w-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true"><path d="M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14zM20 20l-3.5-3.5"/></svg>
                <input type="search" class="form-control" data-w-filter="search" placeholder="<?php echo esc_attr(sc_t('dashboard_pages.search_brands', 'Search name, email or website')); ?>" autocomplete="off">
                <kbd class="w-search__kbd" aria-hidden="true">/</kbd>
            </label>
            <select class="form-control" data-w-filter="event_id" aria-label="<?php echo esc_attr(sc_t('dashboard_pages.all_events', 'All events')); ?>">
                <option value=""><?php echo esc_html(sc_t('dashboard_pages.all_events', 'All events')); ?></option>
                <?php foreach ($events as $ev): ?>
                    <option value="<?php echo (int) $ev->id; ?>"><?php echo esc_html($ev->title); ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($is_sponsor): ?>
            <select class="form-control" data-w-filter="tier" aria-label="<?php echo esc_attr(sc_t('dashboard_pages.all_tiers', 'All tiers')); ?>">
                <option value=""><?php echo esc_html(sc_t('dashboard_pages.all_tiers', 'All tiers')); ?></option>
                <?php foreach ($tiers as $key => $label): ?>
                    <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
        </div>
        <div class="w-chips" data-w-chips hidden></div>
        <div class="w-bulkbar" data-w-bulk hidden></div>
        <div class="w-table-card" data-w-card aria-live="polite">
            <div class="w-table-card__progress" data-w-progress hidden></div>
            <div class="w-table-scroll" data-w-scroll>
                <table class="w-table" data-w-table><thead></thead><tbody></tbody></table>
            </div>
            <div class="w-state" data-w-state hidden></div>
            <div class="w-pager" data-w-pager hidden></div>
        </div>
    </div>

</div>
</div>

<script>
jQuery(function ($) {
    'use strict';

    var esc = WDList.esc;
    var type = <?php echo $js($brand_type); ?>;
    var isSponsor = type === 'sponsor';
    var dashboardUrl = <?php echo $js($dashboard_url); ?>;
    var tiers = <?php echo $js($tiers); ?>;
    var eventTitles = <?php echo $js(array_reduce($events, function ($c, $e) { $c[(int) $e->id] = $e->title; return $c; }, array())); ?>;
    var L = <?php echo $js(array(
        'all'       => sc_t('dashboard_pages.all', 'All'),
        'active'    => sc_t('dashboard_pages.active', 'Active'),
        'hidden'    => sc_t('dashboard_pages.hidden', 'Hidden'),
        'upcoming'  => sc_t('dashboard_pages.in_upcoming_event', 'In an upcoming event'),
        'unlinked'  => sc_t('dashboard_pages.in_no_event', 'In no event'),
        'name'      => $is_sponsor ? sc_t('dashboard_pages.sponsor', 'Sponsor') : sc_t('dashboard_pages.partner', 'Partner'),
        'tier'      => sc_t('dashboard_pages.tier', 'Tier'),
        'events'    => sc_t('dashboard_pages.events', 'Events'),
        'order'     => sc_t('dashboard_pages.order', 'Order'),
        'none'      => sc_t('dashboard_pages.none', 'None'),
        'upTag'     => sc_t('dashboard_pages.upcoming', 'Upcoming'),
        'edit'      => sc_t('dashboard_pages.edit', 'Edit'),
        'website'   => sc_t('dashboard_pages.open_website', 'Open website'),
        'show'      => sc_t('dashboard_pages.make_active', 'Make active'),
        'hide'      => sc_t('dashboard_pages.hide', 'Hide'),
        'delete'    => sc_t('dashboard_pages.delete', 'Delete'),
        'search'    => sc_t('general.search', 'Search'),
        'event'     => sc_t('events.event', 'Event'),
        'emptyText' => $is_sponsor ? sc_t('dashboard_pages.no_sponsors', 'No sponsors yet.') : sc_t('dashboard_pages.no_partners', 'No partners yet.'),
        'confirmDelete'    => sc_t('dashboard_pages.confirm_delete_brands', 'Delete %d? Their logos come off every event and the Sponsors page. This cannot be undone.'),
        'confirmDeleteOne' => sc_t('dashboard_pages.confirm_delete_brand', 'Delete %s? The logo comes off every event and the Sponsors page. This cannot be undone.'),
        'noLogo'    => sc_t('dashboard_pages.no_logo', 'No logo'),
        'failed'    => sc_t('errors.something_wrong', 'Something went wrong. Please try again.'),
    )); ?>;

    function bulk(op, ids) {
        return $.ajax({ url: scDashboard.ajaxurl, type: 'POST', data: { action: 'sc_bulk_' + type + 's', nonce: scDashboard.nonce, op: op, ids: ids } })
            .done(function (res) {
                if (res.success) { showSuccess(res.data.message); list.reload(); }
                else { showError(res.data && res.data.message ? res.data.message : L.failed); }
            })
            .fail(function () { showError(L.failed); });
    }
    function confirmDelete(ids, name) {
        showDeleteConfirm(name ? L.confirmDeleteOne.replace('%s', name) : L.confirmDelete.replace('%d', ids.length)).then(function (r) {
            if (r.isConfirmed) { bulk('delete', ids); }
        });
    }
    var ICON = {
        pencil: 'M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z',
        external: 'M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6M15 3h6v6M10 14 21 3',
        eye: 'M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12zM12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z',
        eyeOff: 'M17.9 17.9A10 10 0 0 1 12 19c-7 0-11-7-11-7a18 18 0 0 1 5.1-5.9M9.9 4.2A9 9 0 0 1 12 4c7 0 11 8 11 8a18 18 0 0 1-2.2 3.2M1 1l22 22',
        trash: 'M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14'
    };

    var columns = [
        {
            label: L.name,
            render: function (b) {
                var logo = b.logo
                    ? '<span class="w-logo-thumb"><img src="' + esc(b.logo) + '" alt=""></span>'
                    : '<span class="w-logo-thumb w-logo-thumb--empty">' + esc(L.noLogo) + '</span>';
                return '<div class="w-person">' + logo + '<span class="w-person__text"><a class="w-row-title" href="' + esc(dashboardUrl + type + '-edit?id=' + b.id) + '">' + esc(b.name) + '</a>' +
                    '<span class="w-sub w-truncate">' + esc(b.title || (b.website ? b.website.replace(/^https?:\/\//, '') : '')) + '</span></span>' +
                    (b.is_active ? '' : ' <span class="w-tag">' + esc(L.hidden) + '</span>') + '</div>';
            }
        }
    ];
    if (isSponsor) {
        columns.push({ label: L.tier, render: function (b) { return '<span class="w-tier-tag w-tier-tag--' + esc(b.tier) + '">' + esc(tiers[b.tier] || b.tier) + '</span>'; } });
    }
    columns.push(
        {
            label: L.events,
            render: function (b) {
                if (!b.events) { return '<span class="text-muted">' + esc(L.none) + '</span>'; }
                return '<div class="w-stack"><span class="w-num">' + WDList.num(b.events) + '</span>' +
                    (b.latest ? '<span class="w-sub w-truncate">' + esc(b.latest.title) + (b.latest.upcoming ? ' · ' + esc(L.upTag) : '') + '</span>' : '') + '</div>';
            }
        },
        { label: L.order, className: 'w-col-xl', render: function (b) { return '<span class="w-num">' + WDList.num(b.order) + '</span>'; } }
    );

    var list = WDList.create({
        root: document.getElementById('brands-list'),
        action: 'sc_get_' + type + 's_paginated',
        rowsKey: 'rows',
        filters: isSponsor ? ['search', 'event_id', 'tier'] : ['search', 'event_id'],
        perPage: 50,
        perPageOptions: [50, 100, 200],
        tabs: [
            { key: 'all', label: L.all, params: { view: 'all' }, countKey: 'all' },
            { key: 'upcoming', label: L.upcoming, params: { view: 'upcoming' }, countKey: 'upcoming' },
            { key: 'active', label: L.active, params: { view: 'active' }, countKey: 'active' },
            { key: 'hidden', label: L.hidden, params: { view: 'hidden' }, countKey: 'hidden' },
            { key: 'unlinked', label: L.unlinked, params: { view: 'unlinked' }, countKey: 'unlinked' }
        ],
        emptyText: L.emptyText,
        columns: columns,
        rowMenu: function (b) {
            return [
                { label: L.edit, icon: ICON.pencil, href: dashboardUrl + type + '-edit?id=' + b.id },
                { label: L.website, icon: ICON.external, href: b.website, disabled: !b.website },
                b.is_active
                    ? { label: L.hide, icon: ICON.eyeOff, onSelect: function () { bulk('hide', [b.id]); } }
                    : { label: L.show, icon: ICON.eye, onSelect: function () { bulk('show', [b.id]); } },
                { separator: true },
                { label: L.delete, icon: ICON.trash, danger: true, onSelect: function () { confirmDelete([b.id], b.name); } }
            ];
        },
        bulkActions: [
            { key: 'show', label: L.show, icon: ICON.eye, run: function (ids) { bulk('show', ids); } },
            { key: 'hide', label: L.hide, icon: ICON.eyeOff, run: function (ids) { bulk('hide', ids); } },
            { key: 'delete', label: L.delete, icon: ICON.trash, danger: true, run: function (ids) { confirmDelete(ids); } }
        ],
        chips: function (state) {
            var f = state.filters, chips = [];
            if (f.search) { chips.push({ label: L.search, value: f.search, clear: function (l) { l.setFilter('search', ''); } }); }
            if (f.event_id) { chips.push({ label: L.event, value: eventTitles[f.event_id] || ('#' + f.event_id), clear: function (l) { l.setFilter('event_id', ''); } }); }
            if (f.tier) { chips.push({ label: L.tier, value: tiers[f.tier] || f.tier, clear: function (l) { l.setFilter('tier', ''); } }); }
            return chips;
        }
    });
});
</script>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
