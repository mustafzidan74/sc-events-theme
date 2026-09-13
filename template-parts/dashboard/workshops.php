<?php
/**
 * Workshops — list pattern (wd-list.js) over sc_get_workshops_paginated.
 *
 * Tabs split by status; seats, check-ins and revenue are live counts from the
 * attendees table, not the cached totals on the workshop row.
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

$events = class_exists('SC_Event') ? SC_Event::get_all(array(
    'status'  => array('publish', 'completed', 'draft'),
    'limit'   => 1000,
    'orderby' => 'start_date',
    'order'   => 'DESC',
)) : array();

$dashboard_url = home_url('/event-manager-dashboard/');

// JSON for inline <script>: hex-escape < > & ' " so no value can close the tag.
$js = function ($value) {
    return wp_json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
};

$t = array(
    'title'      => sc_t('dashboard_pages.workshops', 'Workshops'),
    'subtitle'   => sc_t('dashboard_pages.workshops_subtitle', 'Hands-on sessions sold alongside an event, each with its own seats and tickets.'),
    'add'        => sc_t('dashboard_pages.add_workshop', 'Add workshop'),
    'search'     => sc_t('dashboard_pages.search_workshops', 'Search title, venue or event'),
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
            <a class="btn btn-primary" id="add-workshop" href="<?php echo esc_url($dashboard_url . 'workshop-create'); ?>"><i class="fa fa-plus" aria-hidden="true"></i> <?php echo esc_html($t['add']); ?></a>
        </div>
    </div>

    <div id="workshops-list">
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
                <?php foreach ($events as $event): ?>
                    <option value="<?php echo (int) $event->id; ?>"><?php echo esc_html($event->title); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="w-chips" data-w-chips hidden></div>

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
    var eventTitles = <?php echo $js(array_reduce($events, function ($carry, $e) {
        $carry[(int) $e->id] = $e->title;
        return $carry;
    }, array())); ?>;
    var L = <?php echo $js(array(
        'all'           => sc_t('dashboard_pages.all', 'All'),
        'published'     => sc_t('dashboard_pages.publish', 'Published'),
        'drafts'        => sc_t('dashboard_pages.drafts', 'Drafts'),
        'completed'     => sc_t('dashboard_pages.completed', 'Completed'),
        'hidden'        => sc_t('dashboard_pages.hidden', 'Hidden'),
        'statuses'      => array(
            'publish'   => sc_t('dashboard_pages.publish', 'Published'),
            'draft'     => sc_t('dashboard_pages.draft', 'Draft'),
            'completed' => sc_t('dashboard_pages.completed', 'Completed'),
            'private'   => sc_t('dashboard_pages.private', 'Private'),
            'cancelled' => sc_t('dashboard_pages.cancelled', 'Cancelled'),
            'disabled'  => sc_t('dashboard_pages.disabled', 'Disabled'),
        ),
        'add'           => $t['add'],
        'workshop'      => sc_t('dashboard_pages.workshop', 'Workshop'),
        'event'         => sc_t('events.event', 'Event'),
        'date'          => sc_t('dashboard_pages.date', 'Date'),
        'seats'         => sc_t('dashboard_pages.seats', 'Seats'),
        'checkedIn'     => sc_t('dashboard_pages.checked_in', 'Checked in'),
        'revenue'       => sc_t('dashboard_pages.revenue', 'Revenue'),
        'status'        => sc_t('dashboard_pages.status', 'Status'),
        'online'        => sc_t('dashboard_pages.online', 'Online'),
        'hybrid'        => sc_t('dashboard_pages.hybrid', 'Hybrid'),
        'noLimit'       => sc_t('dashboard_pages.no_seat_limit', 'No seat limit'),
        'full'          => sc_t('dashboard_pages.full', 'Full'),
        'noTickets'     => sc_t('dashboard_pages.no_tickets', 'No tickets yet'),
        'ended'         => sc_t('dashboard_pages.ended', 'Ended'),
        'today'         => sc_t('dashboard_pages.today', 'Today'),
        'tomorrow'      => sc_t('dashboard_pages.tomorrow', 'Tomorrow'),
        'inDays'        => sc_t('dashboard_pages.in_n_days', 'In %d days'),
        'deleted'       => sc_t('dashboard_pages.deleted_event', 'Event deleted'),
        'currency'      => get_option('sc_currency_code', 'EGP'),
        'edit'          => sc_t('dashboard_pages.edit', 'Edit'),
        'attendees'     => sc_t('nav.attendees', 'Attendees'),
        'scanner'       => sc_t('dashboard_pages.scanner', 'Scanner'),
        'viewOnSite'    => sc_t('dashboard_pages.view_on_site', 'View on site'),
        'preview'       => sc_t('dashboard_pages.preview', 'Preview'),
        'delete'        => sc_t('dashboard_pages.delete', 'Delete'),
        'search'        => sc_t('general.search', 'Search'),
        'emptyText'     => sc_t('dashboard_pages.no_workshops_yet', 'No workshops yet. Add one and attach it to an event.'),
        'confirmDelete' => sc_t('dashboard_pages.confirm_delete_workshop', 'Delete “%s”? Its tickets are deleted too. This cannot be undone.'),
        'confirmDeleteRegs' => sc_t('dashboard_pages.confirm_delete_workshop_regs', 'Delete “%s”? Its %d registrations, their check-ins and certificates, and its tickets are deleted too. This cannot be undone.'),
        'deletedOk'     => sc_t('dashboard_pages.workshop_deleted', 'Workshop deleted.'),
        'failed'        => sc_t('errors.something_wrong', 'Something went wrong. Please try again.'),
    )); ?>;

    var DAY = 86400000;
    function parseDate(d, t) { return d ? new Date(d + 'T' + (t || '00:00:00')) : null; }
    function fmtDate(d) { return d ? d.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' }) : ''; }
    function fmtTime(t) { return t ? t.slice(0, 5) : ''; }
    function money(n) { return Number(n).toLocaleString('en-US', { maximumFractionDigits: 2 }) + ' ' + L.currency; }
    function stack(html) { return '<div class="w-stack">' + html + '</div>'; }
    function startOfDay(d) { return new Date(d.getFullYear(), d.getMonth(), d.getDate()); }

    function when(w) {
        var start = parseDate(w.start_date);
        var end = parseDate(w.end_date || w.start_date, w.end_time || '23:59:59');
        if (!start) { return ''; }
        var now = new Date();
        if (end < now) { return L.ended; }
        var days = Math.round((startOfDay(start) - startOfDay(now)) / DAY);
        if (days <= 0) { return L.today; }
        return days === 1 ? L.tomorrow : L.inDays.replace('%d', days);
    }

    var STATUS_TONE = { publish: 'teal', cancelled: 'red' };

    var ICON = {
        pencil: 'M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z',
        users: 'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM23 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8',
        scan: 'M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2M7 12h10',
        external: 'M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6M15 3h6v6M10 14 21 3',
        trash: 'M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14'
    };

    var list = WDList.create({
        root: document.getElementById('workshops-list'),
        action: 'sc_get_workshops_paginated',
        rowsKey: 'workshops',
        filters: ['search', 'event_id'],
        perPage: 25,
        defaultSort: { orderby: 'start_date', order: 'desc' },
        tabs: [
            { key: 'all', label: L.all, countKey: 'all' },
            { key: 'publish', label: L.published, params: { status: 'publish' }, countKey: 'publish' },
            { key: 'draft', label: L.drafts, params: { status: 'draft' }, countKey: 'draft' },
            { key: 'completed', label: L.completed, params: { status: 'completed' }, countKey: 'completed' },
            { key: 'hidden', label: L.hidden, params: { status: 'hidden' }, countKey: 'hidden' }
        ],
        emptyText: L.emptyText,
        emptyAction: '<a class="btn btn-primary" href="' + esc(dashboardUrl + 'workshop-create') + '">' + esc(L.add) + '</a>',
        columns: [
            {
                label: L.workshop, sort: 'title',
                render: function (w) {
                    var where = w.location_type === 'online' ? L.online
                        : [w.venue_name, w.location_type === 'hybrid' ? L.hybrid : ''].filter(Boolean).join(' · ');
                    return '<div class="w-stack"><a class="w-row-title" href="' + esc(dashboardUrl + 'workshop-edit?id=' + w.id) + '">' + esc(w.title) + '</a>' +
                        (where ? '<span class="w-sub w-truncate">' + esc(where) + '</span>' : '') + '</div>';
                }
            },
            {
                label: L.event,
                render: function (w) {
                    return w.event_deleted
                        ? '<span class="w-tag w-tag--red">' + esc(L.deleted) + '</span>'
                        : '<span class="w-truncate">' + esc(w.event_title) + '</span>';
                }
            },
            {
                label: L.date, sort: 'start_date',
                render: function (w) {
                    var multi = w.end_date && w.end_date !== w.start_date;
                    var text = fmtDate(parseDate(w.start_date)) + (multi ? ' – ' + fmtDate(parseDate(w.end_date)) : '');
                    var time = w.start_time ? fmtTime(w.start_time) + (w.end_time ? '–' + fmtTime(w.end_time) : '') : '';
                    return stack('<span class="w-ltr w-nowrap">' + esc(text) + '</span><span class="w-sub">' + esc([time, when(w)].filter(Boolean).join(' · ')) + '</span>');
                }
            },
            {
                label: L.seats,
                render: function (w) {
                    if (!w.tickets) { return stack('<span class="w-num">' + WDList.num(w.registered) + '</span><span class="w-sub">' + esc(L.noTickets) + '</span>'); }
                    if (!w.total_capacity) { return stack('<span class="w-num">' + WDList.num(w.registered) + '</span><span class="w-sub">' + esc(L.noLimit) + '</span>'); }
                    var pct = Math.min(100, Math.round(w.registered / w.total_capacity * 100));
                    var tone = pct >= 100 ? ' is-full' : pct >= 80 ? ' is-high' : '';
                    return stack('<span class="w-num w-nowrap">' + WDList.num(w.registered) + ' / ' + WDList.num(w.total_capacity) +
                        (pct >= 100 ? ' <span class="w-tag w-tag--red">' + esc(L.full) + '</span>' : '') + '</span>' +
                        '<span class="w-bar" role="img" aria-label="' + pct + '%"><span class="w-bar__fill' + tone + '" style="width:' + pct + '%"></span></span>');
                }
            },
            {
                label: L.checkedIn, className: 'w-col-xl',
                render: function (w) {
                    return w.registered
                        ? stack('<span class="w-num">' + WDList.num(w.checked_in) + '</span><span class="w-sub">' + Math.round(w.checked_in / w.registered * 100) + '%</span>')
                        : '<span class="text-muted">—</span>';
                }
            },
            {
                label: L.revenue, className: 'w-col-xl',
                render: function (w) { return w.revenue > 0 ? '<span class="w-num w-ltr w-nowrap">' + esc(money(w.revenue)) + '</span>' : '<span class="text-muted">—</span>'; }
            },
            {
                label: L.status,
                render: function (w) {
                    var tone = STATUS_TONE[w.status];
                    return '<span class="w-tag' + (tone ? ' w-tag--' + tone : '') + '">' + esc(L.statuses[w.status] || w.status) + '</span>';
                }
            }
        ],
        rowMenu: function (w) {
            var live = w.status === 'publish' || w.status === 'completed';
            return [
                { label: L.edit, icon: ICON.pencil, href: dashboardUrl + 'workshop-edit?id=' + w.id },
                { label: L.attendees, icon: ICON.users, href: dashboardUrl + 'workshop-attendees?workshop_id=' + w.id },
                { label: L.scanner, icon: ICON.scan, href: dashboardUrl + 'workshop-scanner?workshop_id=' + w.id },
                { label: live ? L.viewOnSite : L.preview, icon: ICON.external, href: w.url },
                { separator: true },
                { label: L.delete, icon: ICON.trash, danger: true, onSelect: function () { deleteWorkshop(w); } }
            ];
        },
        chips: function (state) {
            var f = state.filters, chips = [];
            if (f.search) { chips.push({ label: L.search, value: f.search, clear: function (l) { l.setFilter('search', ''); } }); }
            if (f.event_id) { chips.push({ label: L.event, value: eventTitles[f.event_id] || ('#' + f.event_id), clear: function (l) { l.setFilter('event_id', ''); } }); }
            return chips;
        },
        onFiltersChange: function (f) {
            // "Add workshop" starts under the event being looked at.
            document.getElementById('add-workshop').href = dashboardUrl + 'workshop-create' + (f.event_id ? '?event_id=' + encodeURIComponent(f.event_id) : '');
        }
    });

    function deleteWorkshop(w) {
        var message = w.registered
            ? L.confirmDeleteRegs.replace('%s', w.title).replace('%d', WDList.num(w.registered))
            : L.confirmDelete.replace('%s', w.title);
        showDeleteConfirm(message).then(function (r) {
            if (!r.isConfirmed) { return; }
            $.ajax({ url: scDashboard.ajaxurl, type: 'POST', data: { action: 'sc_delete_workshop', nonce: scDashboard.nonce, id: w.id } })
                .done(function (res) {
                    if (res.success) { showSuccess(L.deletedOk); list.reload(); }
                    else { showError(res.data && res.data.message ? res.data.message : L.failed); }
                })
                .fail(function () { showError(L.failed); });
        });
    }
});
</script>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
