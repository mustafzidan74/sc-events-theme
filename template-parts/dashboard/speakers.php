<?php
/**
 * Speakers — list pattern over sc_get_speakers_paginated.
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
$dashboard_url = home_url('/event-manager-dashboard/');
$js = function ($value) {
    return wp_json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
};
$t = array(
    'title'      => sc_t('nav.speakers', 'Speakers'),
    'subtitle'   => sc_t('dashboard_pages.speakers_subtitle', 'People on the programme. Add them to an event from the event’s Speakers section.'),
    'add'        => sc_t('dashboard_pages.add_speaker', 'Add speaker'),
    'search'     => sc_t('dashboard_pages.search_speakers', 'Search name, title, company or email'),
    'all_events' => sc_t('dashboard_pages.all_events', 'All events'),
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
            <a class="btn btn-secondary" href="<?php echo esc_url(home_url('/speakers/')); ?>" target="_blank" rel="noopener"><i class="fa fa-external-link" aria-hidden="true"></i> <?php echo esc_html(sc_t('dashboard_pages.view_on_site', 'View on site')); ?></a>
            <a class="btn btn-primary" href="<?php echo esc_url($dashboard_url . 'speaker-create'); ?>"><i class="fa fa-plus" aria-hidden="true"></i> <?php echo esc_html($t['add']); ?></a>
        </div>
    </div>

    <div id="speakers-list">
        <div class="w-tabs" role="tablist" data-w-tabs aria-label="<?php echo esc_attr($t['title']); ?>"></div>

        <div class="w-toolbar">
            <label class="w-search">
                <span class="sr-only"><?php echo esc_html($t['search']); ?></span>
                <svg class="w-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true"><path d="M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14zM20 20l-3.5-3.5"/></svg>
                <input type="search" class="form-control" data-w-filter="search" placeholder="<?php echo esc_attr($t['search']); ?>" autocomplete="off">
                <kbd class="w-search__kbd" aria-hidden="true">/</kbd>
            </label>
            <select class="form-control" data-w-filter="event_id" aria-label="<?php echo esc_attr($t['all_events']); ?>">
                <option value=""><?php echo esc_html($t['all_events']); ?></option>
                <?php foreach ($events as $ev): ?>
                    <option value="<?php echo (int) $ev->id; ?>"><?php echo esc_html($ev->title); ?></option>
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

<script>
jQuery(function ($) {
    'use strict';

    var esc = WDList.esc;
    var dashboardUrl = <?php echo $js($dashboard_url); ?>;
    var eventTitles = <?php echo $js(array_reduce($events, function ($c, $e) { $c[(int) $e->id] = $e->title; return $c; }, array())); ?>;
    var L = <?php echo $js(array(
        'active'       => sc_t('dashboard_pages.on_site', 'On the site'),
        'hidden'       => sc_t('dashboard_pages.hidden', 'Hidden'),
        'upcoming'     => sc_t('dashboard_pages.in_upcoming_event', 'In an upcoming event'),
        'unlinked'     => sc_t('dashboard_pages.in_no_event', 'In no event'),
        'speaker'      => sc_t('dashboard_pages.speaker', 'Speaker'),
        'latest'       => sc_t('dashboard_pages.latest_event', 'Latest event'),
        'events'       => sc_t('dashboard_pages.events', 'Events'),
        'contact'      => sc_t('dashboard_pages.contact', 'Contact'),
        'order'        => sc_t('dashboard_pages.order', 'Order'),
        'upcomingTag'  => sc_t('dashboard_pages.upcoming', 'Upcoming'),
        'hiddenTag'    => sc_t('dashboard_pages.hidden', 'Hidden'),
        'none'         => sc_t('dashboard_pages.none', 'None'),
        'edit'         => sc_t('dashboard_pages.edit', 'Edit'),
        'view'         => sc_t('dashboard_pages.view_on_site', 'View on site'),
        'show'         => sc_t('dashboard_pages.show_on_site', 'Show on site'),
        'hide'         => sc_t('dashboard_pages.hide_from_site', 'Hide from site'),
        'delete'       => sc_t('dashboard_pages.delete', 'Delete'),
        'search'       => sc_t('general.search', 'Search'),
        'event'        => sc_t('events.event', 'Event'),
        'emptyText'    => sc_t('dashboard_pages.no_speakers', 'No speakers yet.'),
        'confirmDelete'=> sc_t('dashboard_pages.confirm_delete_speakers', 'Delete %d speakers? They are removed from every event they are in. This cannot be undone.'),
        'confirmDeleteOne' => sc_t('dashboard_pages.confirm_delete_speaker', 'Delete %s? They are removed from every event they are in. This cannot be undone.'),
        'failed'       => sc_t('errors.something_wrong', 'Something went wrong. Please try again.'),
    )); ?>;

    function initials(name) {
        var parts = String(name || '').trim().split(/\s+/).filter(Boolean);
        return ((parts[0] || '?').charAt(0) + (parts.length > 1 ? parts[1].charAt(0) : '')).toUpperCase();
    }
    function bulk(op, ids) {
        return $.ajax({ url: scDashboard.ajaxurl, type: 'POST', data: { action: 'sc_bulk_speakers', nonce: scDashboard.nonce, op: op, ids: ids } })
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
        root: document.getElementById('speakers-list'),
        action: 'sc_get_speakers_paginated',
        rowsKey: 'speakers',
        filters: ['search', 'event_id'],
        perPage: 50,
        perPageOptions: [50, 100, 200],
        defaultSort: { orderby: 'name', order: 'asc' },
        tabs: [
            { key: 'active', label: L.active, params: { view: 'active' }, countKey: 'active' },
            { key: 'upcoming', label: L.upcoming, params: { view: 'upcoming' }, countKey: 'upcoming' },
            { key: 'unlinked', label: L.unlinked, params: { view: 'unlinked' }, countKey: 'unlinked' },
            { key: 'hidden', label: L.hidden, params: { view: 'hidden' }, countKey: 'hidden' }
        ],
        emptyText: L.emptyText,
        columns: [
            {
                label: L.speaker, sort: 'name',
                render: function (s) {
                    var pic = s.photo
                        ? '<img class="w-person__avatar w-person__avatar--img" src="' + esc(s.photo) + '" alt="" loading="lazy">'
                        : '<span class="w-person__avatar" data-tone="' + (s.id % 4) + '" aria-hidden="true">' + esc(initials(s.name)) + '</span>';
                    var sub = [s.title, s.company].filter(Boolean).join(' · ');
                    return '<div class="w-person">' + pic + '<span class="w-person__text"><a class="w-row-title" href="' + esc(dashboardUrl + 'speaker-edit?id=' + s.id) + '">' + esc(s.name) + '</a>' +
                        (sub ? '<span class="w-sub w-truncate">' + esc(sub) + '</span>' : '') + '</span>' +
                        (s.is_active ? '' : ' <span class="w-tag">' + esc(L.hiddenTag) + '</span>') + '</div>';
                }
            },
            {
                label: L.latest,
                render: function (s) {
                    if (!s.latest_event) { return '<span class="text-muted">' + esc(L.none) + '</span>'; }
                    return '<div class="w-stack"><span class="w-truncate">' + esc(s.latest_event.title) + '</span>' +
                        (s.latest_event.upcoming ? '<span class="w-tag w-tag--teal">' + esc(L.upcomingTag) + '</span>' : '') + '</div>';
                }
            },
            {
                label: L.events, sort: 'events',
                render: function (s) { return '<span class="w-num">' + WDList.num(s.events) + '</span>'; }
            },
            {
                label: L.contact, className: 'w-col-xl',
                render: function (s) {
                    if (!s.email && !s.phone) { return '<span class="text-muted">—</span>'; }
                    return '<div class="w-stack">' + (s.email ? '<span class="w-ltr w-truncate">' + esc(s.email) + '</span>' : '') + (s.phone ? '<span class="w-sub w-ltr">' + esc(s.phone) + '</span>' : '') + '</div>';
                }
            },
            {
                label: L.order, sort: 'display_order', className: 'w-col-xl',
                render: function (s) { return '<span class="w-num">' + WDList.num(s.display_order) + '</span>'; }
            }
        ],
        rowMenu: function (s) {
            return [
                { label: L.edit, icon: ICON.pencil, href: dashboardUrl + 'speaker-edit?id=' + s.id },
                { label: L.view, icon: ICON.external, href: s.url, disabled: !s.is_active },
                s.is_active
                    ? { label: L.hide, icon: ICON.eyeOff, onSelect: function () { bulk('hide', [s.id]); } }
                    : { label: L.show, icon: ICON.eye, onSelect: function () { bulk('show', [s.id]); } },
                { separator: true },
                { label: L.delete, icon: ICON.trash, danger: true, onSelect: function () { confirmDelete([s.id], s.name); } }
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
            return chips;
        }
    });
});
</script>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
