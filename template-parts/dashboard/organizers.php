<?php
/**
 * Organizers — list pattern over sc_get_organizers_paginated
 * (inc/admin-dashboard/organizers-dashboard.php).
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

global $load_wd_list;
$load_wd_list = true;

$dashboard_url = home_url('/event-manager-dashboard/');
$js = function ($value) {
    return wp_json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
};
$t = array(
    'title'    => sc_t('nav.organizers', 'Organizers'),
    'subtitle' => sc_t('dashboard_pages.organizers_subtitle', 'The companies and bodies behind your events. Link them to an event in the event form’s People section.'),
    'add'      => sc_t('dashboard_pages.add_organizer', 'Add organizer'),
    'search'   => sc_t('dashboard_pages.search_organizers', 'Search name, email or website'),
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
            <a class="btn btn-primary" href="<?php echo esc_url($dashboard_url . 'organizer-create'); ?>"><i class="fa fa-plus" aria-hidden="true"></i> <?php echo esc_html($t['add']); ?></a>
        </div>
    </div>

    <div id="organizers-list">
        <div class="w-tabs" role="tablist" data-w-tabs aria-label="<?php echo esc_attr($t['title']); ?>"></div>
        <div class="w-toolbar">
            <label class="w-search">
                <span class="sr-only"><?php echo esc_html($t['search']); ?></span>
                <svg class="w-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true"><path d="M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14zM20 20l-3.5-3.5"/></svg>
                <input type="search" class="form-control" data-w-filter="search" placeholder="<?php echo esc_attr($t['search']); ?>" autocomplete="off">
                <kbd class="w-search__kbd" aria-hidden="true">/</kbd>
            </label>
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
    var dashboardUrl = <?php echo $js($dashboard_url); ?>;
    var L = <?php echo $js(array(
        'all'       => sc_t('dashboard_pages.all', 'All'),
        'active'    => sc_t('dashboard_pages.active', 'Active'),
        'hidden'    => sc_t('dashboard_pages.hidden', 'Hidden'),
        'unlinked'  => sc_t('dashboard_pages.in_no_event', 'In no event'),
        'organizer' => sc_t('dashboard_pages.organizer', 'Organizer'),
        'contact'   => sc_t('dashboard_pages.contact', 'Contact'),
        'events'    => sc_t('dashboard_pages.events', 'Events'),
        'latest'    => sc_t('dashboard_pages.latest_event', 'Latest event'),
        'order'     => sc_t('dashboard_pages.order', 'Order'),
        'none'      => sc_t('dashboard_pages.none', 'None'),
        'edit'      => sc_t('dashboard_pages.edit', 'Edit'),
        'website'   => sc_t('dashboard_pages.open_website', 'Open website'),
        'show'      => sc_t('dashboard_pages.make_active', 'Make active'),
        'hide'      => sc_t('dashboard_pages.hide', 'Hide'),
        'delete'    => sc_t('dashboard_pages.delete', 'Delete'),
        'search'    => sc_t('general.search', 'Search'),
        'emptyText' => sc_t('dashboard_pages.no_organizers', 'No organizers yet.'),
        'confirmDelete'    => sc_t('dashboard_pages.confirm_delete_organizers', 'Delete %d organizers? They are removed from every event. This cannot be undone.'),
        'confirmDeleteOne' => sc_t('dashboard_pages.confirm_delete_organizer', 'Delete %s? They are removed from every event. This cannot be undone.'),
        'failed'    => sc_t('errors.something_wrong', 'Something went wrong. Please try again.'),
    )); ?>;

    function initials(name) {
        var parts = String(name || '').trim().split(/\s+/).filter(Boolean);
        return ((parts[0] || '?').charAt(0) + (parts.length > 1 ? parts[1].charAt(0) : '')).toUpperCase();
    }
    function bulk(op, ids) {
        return $.ajax({ url: scDashboard.ajaxurl, type: 'POST', data: { action: 'sc_bulk_organizers', nonce: scDashboard.nonce, op: op, ids: ids } })
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

    var list = WDList.create({
        root: document.getElementById('organizers-list'),
        action: 'sc_get_organizers_paginated',
        rowsKey: 'rows',
        filters: ['search'],
        perPage: 50,
        perPageOptions: [50, 100, 200],
        defaultSort: { orderby: 'name', order: 'asc' },
        tabs: [
            { key: 'all', label: L.all, params: { view: 'all' }, countKey: 'all' },
            { key: 'active', label: L.active, params: { view: 'active' }, countKey: 'active' },
            { key: 'hidden', label: L.hidden, params: { view: 'hidden' }, countKey: 'hidden' },
            { key: 'unlinked', label: L.unlinked, params: { view: 'unlinked' }, countKey: 'unlinked' }
        ],
        emptyText: L.emptyText,
        columns: [
            {
                label: L.organizer, sort: 'name',
                render: function (o) {
                    var pic = o.logo
                        ? '<img class="w-person__avatar w-person__avatar--img" src="' + esc(o.logo) + '" alt="" loading="lazy">'
                        : '<span class="w-person__avatar" data-tone="' + (o.id % 4) + '" aria-hidden="true">' + esc(initials(o.name)) + '</span>';
                    return '<div class="w-person">' + pic + '<span class="w-person__text"><a class="w-row-title" href="' + esc(dashboardUrl + 'organizer-edit?id=' + o.id) + '">' + esc(o.name) + '</a>' +
                        (o.website ? '<span class="w-sub w-ltr w-truncate">' + esc(o.website.replace(/^https?:\/\//, '')) + '</span>' : '') + '</span>' +
                        (o.is_active ? '' : ' <span class="w-tag">' + esc(L.hidden) + '</span>') + '</div>';
                }
            },
            {
                label: L.contact,
                render: function (o) {
                    if (!o.email && !o.phone) { return '<span class="text-muted">—</span>'; }
                    return '<div class="w-stack">' + (o.email ? '<span class="w-ltr w-truncate">' + esc(o.email) + '</span>' : '') + (o.phone ? '<span class="w-sub w-ltr">' + esc(o.phone) + '</span>' : '') + '</div>';
                }
            },
            {
                label: L.events, sort: 'events',
                render: function (o) {
                    if (!o.events) { return '<span class="text-muted">' + esc(L.none) + '</span>'; }
                    return '<div class="w-stack"><span class="w-num">' + WDList.num(o.events) + '</span>' + (o.latest ? '<span class="w-sub w-truncate">' + esc(o.latest.title) + '</span>' : '') + '</div>';
                }
            },
            {
                label: L.order, sort: 'display_order', className: 'w-col-xl',
                render: function (o) { return '<span class="w-num">' + WDList.num(o.order) + '</span>'; }
            }
        ],
        rowMenu: function (o) {
            return [
                { label: L.edit, icon: ICON.pencil, href: dashboardUrl + 'organizer-edit?id=' + o.id },
                { label: L.website, icon: ICON.external, href: o.website, disabled: !o.website },
                o.is_active
                    ? { label: L.hide, icon: ICON.eyeOff, onSelect: function () { bulk('hide', [o.id]); } }
                    : { label: L.show, icon: ICON.eye, onSelect: function () { bulk('show', [o.id]); } },
                { separator: true },
                { label: L.delete, icon: ICON.trash, danger: true, onSelect: function () { confirmDelete([o.id], o.name); } }
            ];
        },
        bulkActions: [
            { key: 'show', label: L.show, icon: ICON.eye, run: function (ids) { bulk('show', ids); } },
            { key: 'hide', label: L.hide, icon: ICON.eyeOff, run: function (ids) { bulk('hide', ids); } },
            { key: 'delete', label: L.delete, icon: ICON.trash, danger: true, run: function (ids) { confirmDelete(ids); } }
        ],
        chips: function (state) {
            return state.filters.search ? [{ label: L.search, value: state.filters.search, clear: function (l) { l.setFilter('search', ''); } }] : [];
        }
    });
});
</script>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
