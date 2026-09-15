<?php
/**
 * Events — list pattern (wd-list.js) over sc_get_events_paginated.
 *
 * The page used to carry a full in-page event editor whose markup had been
 * removed; editing lives on event-edit / event-create, so only the list stays.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

global $wpdb;
$events_table = $wpdb->prefix . 'sc_events';

// Auto-complete past events (throttled to once per 5 minutes)
if (!get_transient('sc_auto_complete_check')) {
    $rows_updated = $wpdb->query($wpdb->prepare(
        "UPDATE {$events_table} SET status = 'completed'
         WHERE status = 'publish'
         AND (end_date < %s OR (end_date IS NULL AND start_date < %s))",
        current_time('Y-m-d'), current_time('Y-m-d')
    ));
    set_transient('sc_auto_complete_check', 1, 300);
    if ($rows_updated > 0) {
        delete_transient('sc_dashboard_home_stats_v2');
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_sc_analytics_%' OR option_name LIKE '_transient_timeout_sc_analytics_%'");
    }
}

// One-time fix: correct payment_method inconsistencies
if (!get_option('sc_payment_method_fix_v2')) {
    $attendees_table = $wpdb->prefix . 'sc_attendees';
    // Fix 1: coupon_code set but payment_method not 'coupon'
    $wpdb->query(
        "UPDATE {$attendees_table} SET payment_method = 'coupon'
         WHERE coupon_code IS NOT NULL AND coupon_code != ''
         AND payment_method != 'coupon'"
    );
    // Fix 2: amount_paid > 0 but payment_method is empty/null (should be 'paid')
    $wpdb->query(
        "UPDATE {$attendees_table} SET payment_method = 'paid'
         WHERE amount_paid > 0
         AND (payment_method IS NULL OR payment_method = '' OR payment_method = 'free')
         AND (coupon_code IS NULL OR coupon_code = '')"
    );
    update_option('sc_payment_method_fix_v2', 1);
}

global $load_wd_list;
$load_wd_list = true;

$categories = taxonomy_exists('sc_event_category') ? get_terms(array(
    'taxonomy'   => 'sc_event_category',
    'hide_empty' => false,
    'orderby'    => 'name',
    'order'      => 'ASC',
)) : array();
if (is_wp_error($categories)) {
    $categories = array();
}

$dashboard_url = home_url('/event-manager-dashboard/');

// JSON for inline <script>: hex-escape < > & ' " so no value can close the tag.
$js = function ($value) {
    return wp_json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
};

$t = array(
    'title'          => sc_t('nav.events', 'Events'),
    'subtitle'       => sc_t('dashboard_pages.events_subtitle', 'Congresses and forums with their own tickets, workshops and attendees.'),
    'add'            => sc_t('dashboard_pages.create_event', 'Create event'),
    'search'         => sc_t('dashboard_pages.search_events', 'Search title, venue or city'),
    'all_categories' => sc_t('dashboard_pages.all_categories', 'All categories'),
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
            <a class="btn btn-primary" href="<?php echo esc_url($dashboard_url . 'event-create'); ?>"><i class="fa fa-plus" aria-hidden="true"></i> <?php echo esc_html($t['add']); ?></a>
        </div>
    </div>

    <div id="events-list">
        <div class="w-tabs" role="tablist" data-w-tabs aria-label="<?php echo esc_attr($t['title']); ?>"></div>

        <div class="w-toolbar">
            <label class="w-search">
                <span class="sr-only"><?php echo esc_html($t['search']); ?></span>
                <svg class="w-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true"><path d="M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14zM20 20l-3.5-3.5"/></svg>
                <input type="search" class="form-control" data-w-filter="search" placeholder="<?php echo esc_attr($t['search']); ?>" autocomplete="off">
                <kbd class="w-search__kbd" aria-hidden="true">/</kbd>
            </label>

            <?php if ($categories): ?>
                <select class="form-control" data-w-filter="category_id" aria-label="<?php echo esc_attr($t['all_categories']); ?>">
                    <option value=""><?php echo esc_html($t['all_categories']); ?></option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo (int) $category->term_id; ?>"><?php echo esc_html($category->name); ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
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
    var num = WDList.num;
    var dashboardUrl = <?php echo $js($dashboard_url); ?>;
    var categoryNames = <?php echo $js(array_reduce($categories, function ($carry, $c) {
        $carry[(int) $c->term_id] = $c->name;
        return $carry;
    }, array())); ?>;
    var L = <?php echo $js(array(
        'all'             => sc_t('dashboard_pages.all', 'All'),
        'upcoming'        => sc_t('dashboard_pages.upcoming', 'Upcoming'),
        'past'            => sc_t('dashboard_pages.past', 'Past'),
        'drafts'          => sc_t('dashboard_pages.drafts', 'Drafts'),
        'completed'       => sc_t('dashboard_pages.completed', 'Completed'),
        'disabled'        => sc_t('dashboard_pages.disabled', 'Disabled'),
        'statuses'        => array(
            'publish'   => sc_t('dashboard_pages.publish', 'Published'),
            'draft'     => sc_t('dashboard_pages.draft', 'Draft'),
            'completed' => sc_t('dashboard_pages.completed', 'Completed'),
            'private'   => sc_t('dashboard_pages.private', 'Private'),
            'cancelled' => sc_t('dashboard_pages.cancelled', 'Cancelled'),
            'disabled'  => sc_t('dashboard_pages.disabled', 'Disabled'),
        ),
        'add'             => $t['add'],
        'event'           => sc_t('events.event', 'Event'),
        'date'            => sc_t('dashboard_pages.date', 'Date'),
        'registered'      => sc_t('dashboard_pages.registered', 'Registered'),
        'checkedIn'       => sc_t('dashboard_pages.checked_in', 'Checked in'),
        'revenue'         => sc_t('dashboard_pages.revenue', 'Revenue'),
        'status'          => sc_t('dashboard_pages.status', 'Status'),
        'category'        => sc_t('dashboard_pages.category', 'Category'),
        'search'          => sc_t('general.search', 'Search'),
        'online'          => sc_t('dashboard_pages.online', 'Online'),
        'hybrid'          => sc_t('dashboard_pages.hybrid', 'Hybrid'),
        'allDay'          => sc_t('dashboard_pages.all_day', 'All day'),
        'nWorkshops'      => sc_t('dashboard_pages.n_workshops', '%d workshops'),
        'oneWorkshop'     => sc_t('dashboard_pages.one_workshop', '1 workshop'),
        'plusWorkshopReg' => sc_t('dashboard_pages.plus_workshop_regs', '+ %d in workshops'),
        'noTickets'       => sc_t('dashboard_pages.no_tickets', 'No tickets yet'),
        'ended'           => sc_t('dashboard_pages.ended', 'Ended'),
        'now'             => sc_t('dashboard_pages.happening_now', 'Happening now'),
        'tomorrow'        => sc_t('dashboard_pages.tomorrow', 'Tomorrow'),
        'inDays'          => sc_t('dashboard_pages.in_n_days', 'In %d days'),
        'currency'        => get_option('sc_currency_code', 'EGP'),
        'edit'            => sc_t('dashboard_pages.edit', 'Edit'),
        'overview'        => sc_t('dashboard_pages.event_overview', 'Overview'),
        'attendees'       => sc_t('nav.attendees', 'Attendees'),
        'workshops'       => sc_t('dashboard_pages.workshops', 'Workshops'),
        'viewOnSite'      => sc_t('dashboard_pages.view_on_site', 'View on site'),
        'preview'         => sc_t('dashboard_pages.preview', 'Preview'),
        'duplicate'       => sc_t('dashboard_pages.duplicate', 'Duplicate'),
        'disable'         => sc_t('dashboard_pages.disable', 'Disable'),
        'enable'          => sc_t('dashboard_pages.enable', 'Enable'),
        'delete'          => sc_t('dashboard_pages.delete', 'Delete'),
        'emptyText'       => sc_t('dashboard_pages.no_events_yet', 'No events yet. Create one to start selling tickets.'),
        'confirmDuplicate'=> sc_t('dashboard_pages.confirm_duplicate_event', 'Copy “%s” with its tickets as a new draft? Attendees and workshops are not copied.'),
        'confirmDisable'  => sc_t('dashboard_pages.confirm_disable_event', 'Disable “%s”? It disappears from the site and stops selling tickets. Its %d registrations stay.'),
        'confirmEnable'   => sc_t('dashboard_pages.confirm_enable_event', 'Publish “%s” again?'),
        'confirmDelete'   => sc_t('dashboard_pages.confirm_delete_event', 'Delete “%s”? This cannot be undone.'),
        'failed'          => sc_t('errors.something_wrong', 'Something went wrong. Please try again.'),
    )); ?>;

    var DAY = 86400000;
    function parseDate(d, t) { return d ? new Date(d + 'T' + (t || '00:00:00')) : null; }
    function fmtDate(d, withYear) { return d ? d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: withYear ? 'numeric' : undefined }) : ''; }
    function fmtTime(t) { return t ? t.slice(0, 5) : ''; }
    function money(n) { return Number(n).toLocaleString('en-US', { maximumFractionDigits: 2 }) + ' ' + L.currency; }
    function stack(html) { return '<div class="w-stack">' + html + '</div>'; }
    function startOfDay(d) { return new Date(d.getFullYear(), d.getMonth(), d.getDate()); }
    function fill(template, title, n) { return template.replace('%s', title).replace('%d', num(n)); }

    function dateRange(e) {
        var start = parseDate(e.start_date), end = parseDate(e.end_date || e.start_date);
        if (!start) { return ''; }
        if (!e.end_date || e.end_date === e.start_date) { return fmtDate(start, true); }
        if (start.getFullYear() === end.getFullYear()) {
            return start.getMonth() === end.getMonth()
                ? start.getDate() + '–' + fmtDate(end, true)
                : fmtDate(start, false) + ' – ' + fmtDate(end, true);
        }
        return fmtDate(start, true) + ' – ' + fmtDate(end, true);
    }

    function when(e) {
        var start = parseDate(e.start_date);
        if (!start) { return ''; }
        var now = startOfDay(new Date());
        var last = startOfDay(parseDate(e.end_date || e.start_date));
        if (last < now) { return L.ended; }
        var days = Math.round((startOfDay(start) - now) / DAY);
        if (days <= 0) { return L.now; }
        return days === 1 ? L.tomorrow : L.inDays.replace('%d', days);
    }

    var STATUS_TONE = { publish: 'teal', cancelled: 'red', disabled: 'red' };

    var ICON = {
        pencil: 'M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z',
        chart: 'M3 3v18h18M7 15l4-4 3 3 5-6',
        users: 'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM23 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8',
        flask: 'M9 3h6M10 3v6L4 19a2 2 0 0 0 1.7 3h12.6a2 2 0 0 0 1.7-3L14 9V3',
        external: 'M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6M15 3h6v6M10 14 21 3',
        copy: 'M9 9h11v11H9zM5 15H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v1',
        pause: 'M10 15V9M14 15V9M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18z',
        play: 'M10 8l6 4-6 4zM12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18z',
        trash: 'M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14'
    };

    var list = WDList.create({
        root: document.getElementById('events-list'),
        action: 'sc_get_events_paginated',
        rowsKey: 'events',
        filters: ['search', 'category_id'],
        perPage: 25,
        defaultSort: { orderby: 'start_date', order: 'desc' },
        tabs: [
            { key: 'all', label: L.all, params: { view: 'all' }, countKey: 'all' },
            { key: 'upcoming', label: L.upcoming, params: { view: 'upcoming' }, countKey: 'upcoming' },
            { key: 'past', label: L.past, params: { view: 'past' }, countKey: 'past' },
            { key: 'draft', label: L.drafts, params: { view: 'draft' }, countKey: 'draft' },
            { key: 'completed', label: L.completed, params: { view: 'completed' }, countKey: 'completed' },
            { key: 'disabled', label: L.disabled, params: { view: 'disabled' }, countKey: 'disabled' }
        ],
        emptyText: L.emptyText,
        emptyAction: '<a class="btn btn-primary" href="' + esc(dashboardUrl + 'event-create') + '">' + esc(L.add) + '</a>',
        columns: [
            {
                label: L.event, sort: 'title',
                render: function (e) {
                    var where = e.location_type === 'online' ? L.online
                        : [e.venue_name, e.venue_city, e.location_type === 'hybrid' ? L.hybrid : ''].filter(Boolean).join(' · ');
                    var sub = [where, e.workshops ? (e.workshops === 1 ? L.oneWorkshop : L.nWorkshops.replace('%d', e.workshops)) : ''].filter(Boolean).join(' · ');
                    var thumb = e.thumb
                        ? '<img class="w-thumb" src="' + esc(e.thumb) + '" alt="" loading="lazy">'
                        : '<span class="w-thumb w-thumb--empty" aria-hidden="true">' + esc((e.title || '?').charAt(0).toUpperCase()) + '</span>';
                    return '<div class="w-person">' + thumb + '<div class="w-stack"><a class="w-row-title" href="' + esc(dashboardUrl + 'event-edit?id=' + e.id) + '">' + esc(e.title) + '</a>' +
                        (sub ? '<span class="w-sub w-truncate">' + esc(sub) + '</span>' : '') + '</div></div>';
                }
            },
            {
                label: L.date, sort: 'start_date',
                render: function (e) {
                    var time = e.all_day ? L.allDay : (e.start_time ? fmtTime(e.start_time) + (e.end_time && e.end_time !== '00:00:00' ? '–' + fmtTime(e.end_time) : '') : '');
                    return stack('<span class="w-ltr w-nowrap">' + esc(dateRange(e)) + '</span><span class="w-sub">' + esc([time, when(e)].filter(Boolean).join(' · ')) + '</span>');
                }
            },
            {
                label: L.registered,
                render: function (e) {
                    var sub = e.workshop_registrations ? L.plusWorkshopReg.replace('%d', num(e.workshop_registrations)) : (!e.tickets && e.status !== 'completed' ? L.noTickets : '');
                    return stack('<span class="w-num">' + num(e.registered) + '</span>' + (sub ? '<span class="w-sub">' + esc(sub) + '</span>' : ''));
                }
            },
            {
                label: L.checkedIn,
                render: function (e) {
                    return e.registered
                        ? stack('<span class="w-num">' + num(e.checked_in) + '</span><span class="w-sub">' + Math.round(e.checked_in / e.registered * 100) + '%</span>')
                        : '<span class="text-muted">—</span>';
                }
            },
            {
                label: L.revenue, className: 'w-col-xl',
                render: function (e) { return e.revenue > 0 ? '<span class="w-num w-ltr w-nowrap">' + esc(money(e.revenue)) + '</span>' : '<span class="text-muted">—</span>'; }
            },
            {
                label: L.status,
                render: function (e) {
                    var tone = STATUS_TONE[e.status];
                    return '<span class="w-tag' + (tone ? ' w-tag--' + tone : '') + '">' + esc(L.statuses[e.status] || e.status) + '</span>';
                }
            }
        ],
        rowMenu: function (e) {
            var live = e.status === 'publish' || e.status === 'completed';
            var items = [
                { label: L.edit, icon: ICON.pencil, href: dashboardUrl + 'event-edit?id=' + e.id },
                { label: L.overview, icon: ICON.chart, href: dashboardUrl + 'event-view?id=' + e.id },
                { label: L.attendees, icon: ICON.users, href: dashboardUrl + 'attendees?event_id=' + e.id },
                { label: L.workshops, icon: ICON.flask, href: dashboardUrl + 'workshops?event_id=' + e.id },
                { label: live ? L.viewOnSite : L.preview, icon: ICON.external, href: e.url },
                { label: L.duplicate, icon: ICON.copy, onSelect: function () { duplicateEvent(e); } },
                { separator: true }
            ];
            if (e.status === 'disabled') {
                items.push({ label: L.enable, icon: ICON.play, onSelect: function () { setStatus(e, 'publish'); } });
            } else if (e.attendee_rows > 0) {
                items.push({ label: L.disable, icon: ICON.pause, danger: true, onSelect: function () { setStatus(e, 'disabled'); } });
            }
            // Events that people registered for can only be disabled; the server enforces the same rule.
            if (e.attendee_rows === 0) {
                items.push({ label: L.delete, icon: ICON.trash, danger: true, onSelect: function () { deleteEvent(e); } });
            }
            return items;
        },
        chips: function (state) {
            var f = state.filters, chips = [];
            if (f.search) { chips.push({ label: L.search, value: f.search, clear: function (l) { l.setFilter('search', ''); } }); }
            if (f.category_id) { chips.push({ label: L.category, value: categoryNames[f.category_id] || ('#' + f.category_id), clear: function (l) { l.setFilter('category_id', ''); } }); }
            return chips;
        }
    });

    function post(data) {
        return $.ajax({ url: scDashboard.ajaxurl, type: 'POST', data: $.extend({ nonce: scDashboard.nonce }, data) });
    }
    function done(res) {
        if (res && res.success) {
            showSuccess(res.data && res.data.message ? res.data.message : '');
            list.reload();
        } else {
            showError(res && res.data && res.data.message ? res.data.message : L.failed);
        }
    }
    function fail() { showError(L.failed); }

    function duplicateEvent(e) {
        showConfirm(fill(L.confirmDuplicate, e.title)).then(function (r) {
            if (r.isConfirmed) { post({ action: 'sc_duplicate_event', event_id: e.id }).done(done).fail(fail); }
        });
    }
    function setStatus(e, status) {
        var ask = status === 'disabled'
            ? showDeleteConfirm(fill(L.confirmDisable, e.title, e.registered + e.workshop_registrations))
            : showConfirm(fill(L.confirmEnable, e.title));
        ask.then(function (r) {
            if (r.isConfirmed) { post({ action: 'sc_update_event_status', event_id: e.id, status: status }).done(done).fail(fail); }
        });
    }
    function deleteEvent(e) {
        showDeleteConfirm(fill(L.confirmDelete, e.title)).then(function (r) {
            if (r.isConfirmed) { post({ action: 'sc_delete_event', event_id: e.id }).done(done).fail(fail); }
        });
    }
});
</script>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
