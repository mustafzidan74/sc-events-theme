<?php
/**
 * Sessions — list pattern over sc_get_sessions_paginated.
 *
 * Sessions carry registration, session check-in, CME hours and certificates.
 * When an event has published sessions its page shows them in place of the
 * plain programme.
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

get_template_part('template-parts/dashboard/components/dashboard', 'header');
get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');
?>

<div id="main-content">
<div class="container-fluid">

    <div class="w-page-head">
        <div>
            <h1><?php echo esc_html(sc_t('nav.sessions', 'Sessions')); ?><span class="w-page-head__count" data-w-total></span></h1>
            <p class="w-page-head__sub"><?php echo esc_html(sc_t('dashboard_pages.sessions_subtitle', 'Sessions with their own check-in, CME hours and certificates. An event with published sessions shows them instead of its programme.')); ?></p>
        </div>
        <div class="w-page-head__actions">
            <a class="btn btn-primary" id="add-session" href="<?php echo esc_url($dashboard_url . 'session-create'); ?>"><i class="fa fa-plus" aria-hidden="true"></i> <?php echo esc_html(sc_t('dashboard_pages.add_session', 'Add session')); ?></a>
        </div>
    </div>

    <div id="sessions-list">
        <div class="w-tabs" role="tablist" data-w-tabs aria-label="<?php echo esc_attr(sc_t('nav.sessions', 'Sessions')); ?>"></div>
        <div class="w-toolbar">
            <label class="w-search">
                <span class="sr-only"><?php echo esc_html(sc_t('dashboard_pages.search_sessions', 'Search title, track or hall')); ?></span>
                <svg class="w-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true"><path d="M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14zM20 20l-3.5-3.5"/></svg>
                <input type="search" class="form-control" data-w-filter="search" placeholder="<?php echo esc_attr(sc_t('dashboard_pages.search_sessions', 'Search title, track or hall')); ?>" autocomplete="off">
                <kbd class="w-search__kbd" aria-hidden="true">/</kbd>
            </label>
            <select class="form-control" data-w-filter="event_id" aria-label="<?php echo esc_attr(sc_t('dashboard_pages.all_events', 'All events')); ?>">
                <option value=""><?php echo esc_html(sc_t('dashboard_pages.all_events', 'All events')); ?></option>
                <?php foreach ($events as $ev): ?>
                    <option value="<?php echo (int) $ev->id; ?>"><?php echo esc_html($ev->title); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="date" class="form-control w-toolbar__date" data-w-filter="date" aria-label="<?php echo esc_attr(sc_t('dashboard_pages.date', 'Date')); ?>">
        </div>
        <div class="w-chips" data-w-chips hidden></div>
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
    var eventTitles = <?php echo $js(array_reduce($events, function ($c, $e) { $c[(int) $e->id] = $e->title; return $c; }, array())); ?>;
    var L = <?php echo $js(array(
        'all'       => sc_t('dashboard_pages.all', 'All'),
        'statuses'  => array(
            'published' => sc_t('dashboard_pages.status_published', 'Published'),
            'draft'     => sc_t('dashboard_pages.status_draft', 'Draft'),
            'live'      => sc_t('dashboard_pages.status_live', 'Live'),
            'ended'     => sc_t('dashboard_pages.ended', 'Ended'),
            'cancelled' => sc_t('dashboard_pages.status_cancelled', 'Cancelled'),
        ),
        'types'     => array(
            'lecture' => 'Lecture', 'workshop' => 'Workshop', 'panel' => 'Panel', 'keynote' => 'Keynote', 'break' => 'Break', 'networking' => 'Networking',
            'exhibition' => 'Exhibition', 'poster' => 'Poster', 'symposium' => 'Symposium', 'hands_on' => 'Hands-on', 'other' => 'Other',
        ),
        'session'   => sc_t('dashboard_pages.session', 'Session'),
        'event'     => sc_t('events.event', 'Event'),
        'when'      => sc_t('dashboard_pages.when', 'When'),
        'hall'      => sc_t('dashboard_pages.hall', 'Hall'),
        'registered'=> sc_t('dashboard_pages.registered', 'Registered'),
        'attended'  => sc_t('dashboard_pages.attended', 'Attended'),
        'status'    => sc_t('dashboard_pages.status', 'Status'),
        'cme'       => sc_t('dashboard_pages.cme_hours_short', '%s CME h'),
        'cert'      => sc_t('dashboard_pages.certificate', 'Certificate'),
        'noEvent'   => sc_t('dashboard_pages.deleted_event', 'Event deleted'),
        'edit'      => sc_t('dashboard_pages.edit', 'Edit'),
        'attendees' => sc_t('nav.attendees', 'Attendees'),
        'delete'    => sc_t('dashboard_pages.delete', 'Delete'),
        'search'    => sc_t('general.search', 'Search'),
        'date'      => sc_t('dashboard_pages.date', 'Date'),
        'emptyText' => sc_t('dashboard_pages.no_sessions', 'No sessions yet.'),
        'confirmDelete' => sc_t('dashboard_pages.confirm_delete_session', 'Delete “%s”? Its registrations and check-ins are deleted too.'),
        'failed'    => sc_t('errors.something_wrong', 'Something went wrong. Please try again.'),
    )); ?>;
    var fmtDay = function (d) { var x = new Date(d + 'T00:00:00'); return isNaN(x) ? d : x.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' }); };
    var TONE = { published: 'teal', live: 'gold', cancelled: 'red' };
    var ICON = {
        pencil: 'M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z',
        users: 'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM23 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8',
        trash: 'M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14'
    };

    var list = WDList.create({
        root: document.getElementById('sessions-list'),
        action: 'sc_get_sessions_paginated',
        rowsKey: 'sessions',
        filters: ['search', 'event_id', 'date'],
        perPage: 50,
        perPageOptions: [50, 100, 200],
        defaultSort: { orderby: 'session_date', order: 'asc' },
        tabs: [{ key: 'all', label: L.all, countKey: 'all' }].concat(['published', 'draft', 'live', 'ended', 'cancelled'].map(function (k) {
            return { key: k, label: L.statuses[k], params: { status: k }, countKey: k };
        })),
        emptyText: L.emptyText,
        columns: [
            {
                label: L.session, sort: 'title',
                render: function (s) {
                    var sub = [L.types[s.session_type] || s.session_type, s.track].filter(Boolean).join(' · ');
                    var extra = (s.cme_hours ? ' <span class="w-tag w-tag--gold">' + esc(L.cme.replace('%s', s.cme_hours)) + '</span>' : '') + (s.enable_certificate ? ' <span class="w-tag">' + esc(L.cert) + '</span>' : '');
                    return '<div class="w-stack"><a class="w-row-title" href="' + esc(dashboardUrl + 'session-edit?id=' + s.id) + '">' + esc(s.title) + '</a><span class="w-sub">' + esc(sub) + extra + '</span></div>';
                }
            },
            { label: L.event, render: function (s) { return s.event_title ? '<span class="w-truncate">' + esc(s.event_title) + '</span>' : '<span class="w-tag w-tag--red">' + esc(L.noEvent) + '</span>'; } },
            {
                label: L.when, sort: 'session_date',
                render: function (s) { return '<div class="w-stack"><span class="w-nowrap w-ltr">' + esc(fmtDay(s.session_date)) + '</span><span class="w-sub w-ltr">' + esc(s.start + (s.end ? '–' + s.end : '')) + '</span></div>'; }
            },
            { label: L.hall, className: 'w-col-xl', render: function (s) { return s.hall_name ? esc(s.hall_name) : '<span class="text-muted">—</span>'; } },
            {
                label: L.registered, sort: 'registered',
                render: function (s) { return '<span class="w-num w-nowrap">' + WDList.num(s.registered) + (s.capacity ? ' / ' + WDList.num(s.capacity) : '') + '</span>'; }
            },
            { label: L.attended, sort: 'attended', render: function (s) { return '<span class="w-num">' + WDList.num(s.attended) + '</span>'; } },
            { label: L.status, render: function (s) { var t = TONE[s.status]; return '<span class="w-tag' + (t ? ' w-tag--' + t : '') + '">' + esc(L.statuses[s.status] || s.status) + '</span>'; } }
        ],
        rowMenu: function (s) {
            return [
                { label: L.edit, icon: ICON.pencil, href: dashboardUrl + 'session-edit?id=' + s.id },
                { label: L.attendees, icon: ICON.users, href: dashboardUrl + 'session-attendees?id=' + s.id },
                { separator: true },
                { label: L.delete, icon: ICON.trash, danger: true, onSelect: function () { removeSession(s); } }
            ];
        },
        chips: function (state) {
            var f = state.filters, chips = [];
            if (f.search) { chips.push({ label: L.search, value: f.search, clear: function (l) { l.setFilter('search', ''); } }); }
            if (f.event_id) { chips.push({ label: L.event, value: eventTitles[f.event_id] || ('#' + f.event_id), clear: function (l) { l.setFilter('event_id', ''); } }); }
            if (f.date) { chips.push({ label: L.date, value: fmtDay(f.date), clear: function (l) { l.setFilter('date', ''); } }); }
            return chips;
        },
        onFiltersChange: function (f) {
            document.getElementById('add-session').href = dashboardUrl + 'session-create' + (f.event_id ? '?event_id=' + encodeURIComponent(f.event_id) : '');
        }
    });

    function removeSession(s) {
        showDeleteConfirm(L.confirmDelete.replace('%s', s.title)).then(function (r) {
            if (!r.isConfirmed) { return; }
            $.ajax({ url: scDashboard.ajaxurl, type: 'POST', data: { action: 'sc_delete_session', nonce: scDashboard.nonce, session_id: s.id } })
                .done(function (res) { if (res.success) { list.reload(); } else { showError(res.data && res.data.message || L.failed); } })
                .fail(function () { showError(L.failed); });
        });
    }
});
</script>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
