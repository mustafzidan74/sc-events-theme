<?php
/**
 * Workshop attendees — one workshop's registrations with live counts, check-in
 * and undo, and shortcuts to scan, add, export and issue certificates.
 *
 * The list is sc_get_attendees_paginated with the event and workshop fixed.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

global $wpdb, $load_wd_list, $load_wd_overview;
$p = $wpdb->prefix;
$workshop_id = isset($_GET['workshop_id']) ? absint($_GET['workshop_id']) : 0;
$workshop = $workshop_id ? $wpdb->get_row($wpdb->prepare(
    "SELECT w.*, e.title AS event_title FROM {$p}sc_workshops w LEFT JOIN {$p}sc_events e ON e.id = w.event_id WHERE w.id = %d",
    $workshop_id
)) : null;
if (!$workshop) {
    wp_safe_redirect(home_url('/event-manager-dashboard/workshops'));
    exit;
}
$load_wd_list = true;
$load_wd_overview = true;

// Live numbers from registrations, not the cached totals on the workshop row.
$stats = $wpdb->get_row($wpdb->prepare(
    "SELECT COALESCE(SUM(status = 'active' AND payment_status = 'success'), 0) AS registered,
            COALESCE(SUM(status = 'active' AND payment_status = 'success' AND checked_in = 1), 0) AS checked_in,
            COALESCE(SUM(status = 'active' AND payment_status = 'pending'), 0) AS pending
     FROM {$p}sc_attendees WHERE workshop_id = %d",
    $workshop_id
));
$seats = (int) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(quantity), 0) FROM {$p}sc_tickets WHERE workshop_id = %d AND is_active = 1", $workshop_id));
$certs = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}sc_certificates c JOIN {$p}sc_attendees a ON a.id = c.attendee_id WHERE a.workshop_id = %d AND c.status <> 'revoked'", $workshop_id));

$dashboard_url = home_url('/event-manager-dashboard/');
$when = array_filter(array(
    $workshop->start_date ? date_i18n('D j M Y', strtotime($workshop->start_date)) : '',
    $workshop->start_time ? substr($workshop->start_time, 0, 5) . ($workshop->end_time ? '–' . substr($workshop->end_time, 0, 5) : '') : '',
    $workshop->venue_name,
));
$export_url = add_query_arg(array('action' => 'sc_export_attendees_csv', 'nonce' => wp_create_nonce('sc_dashboard_nonce'), 'event_id' => (int) $workshop->event_id, 'workshop_id' => $workshop_id), admin_url('admin-ajax.php'));
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
            <p class="w-page-head__eyebrow"><a href="<?php echo esc_url($dashboard_url . 'event-view?id=' . (int) $workshop->event_id); ?>"><?php echo esc_html($workshop->event_title); ?></a></p>
            <h1><?php echo esc_html($workshop->title); ?></h1>
            <p class="w-page-head__sub"><?php echo esc_html(implode(' · ', $when)); ?></p>
        </div>
        <div class="w-page-head__actions">
            <a class="btn btn-secondary" href="<?php echo esc_url($dashboard_url . 'workshop-edit?id=' . $workshop_id); ?>"><?php echo esc_html(sc_t('dashboard_pages.edit_workshop', 'Edit workshop')); ?></a>
            <a class="btn btn-secondary" href="<?php echo esc_url($export_url); ?>"><i class="fa fa-download" aria-hidden="true"></i> <?php echo esc_html(sc_t('dashboard_pages.export', 'Export')); ?></a>
            <a class="btn btn-secondary" href="<?php echo esc_url($dashboard_url . 'certificate-issue?workshop_id=' . $workshop_id); ?>"><?php echo esc_html(sc_t('dashboard_pages.certificates', 'Certificates')); ?></a>
            <a class="btn btn-secondary" href="<?php echo esc_url($dashboard_url . 'attendee-add?event_id=' . (int) $workshop->event_id . '&workshop_id=' . $workshop_id); ?>"><i class="fa fa-plus" aria-hidden="true"></i> <?php echo esc_html(sc_t('dashboard_pages.add_attendee', 'Add attendee')); ?></a>
            <a class="btn btn-primary" href="<?php echo esc_url($dashboard_url . 'scanner?event_id=' . (int) $workshop->event_id . '&workshop_id=' . $workshop_id); ?>"><i class="fa fa-qrcode" aria-hidden="true"></i> <?php echo esc_html(sc_t('dashboard_pages.open_scanner', 'Open scanner')); ?></a>
        </div>
    </div>

    <div class="w-kpis">
        <div class="w-kpi">
            <span class="w-kpi__label"><?php echo esc_html(sc_t('dashboard_pages.registered', 'Registered')); ?></span>
            <span class="w-kpi__value" data-kpi="registered"><?php echo esc_html(number_format_i18n((int) $stats->registered)); ?></span>
            <span class="w-kpi__sub"><?php echo esc_html($seats ? sprintf(sc_t('dashboard_pages.of_n_seats', 'of %s seats'), number_format_i18n($seats)) : sc_t('dashboard_pages.no_seat_limit', 'No seat limit')); ?></span>
        </div>
        <div class="w-kpi">
            <span class="w-kpi__label"><?php echo esc_html(sc_t('dashboard_pages.checked_in', 'Checked in')); ?></span>
            <span class="w-kpi__value" data-kpi="checked_in"><?php echo esc_html(number_format_i18n((int) $stats->checked_in)); ?></span>
            <span class="w-kpi__sub"><?php echo esc_html($stats->registered ? sprintf(sc_t('dashboard_pages.pct_of_registered', '%s%% of registered'), round($stats->checked_in / $stats->registered * 100)) : '—'); ?></span>
        </div>
        <div class="w-kpi">
            <span class="w-kpi__label"><?php echo esc_html(sc_t('dashboard_pages.seats_left', 'Seats left')); ?></span>
            <span class="w-kpi__value"><?php echo esc_html($seats ? number_format_i18n(max(0, $seats - (int) $stats->registered)) : '∞'); ?></span>
            <span class="w-kpi__sub"><?php echo esc_html((int) $stats->pending ? sprintf(sc_t('dashboard_pages.n_payment_pending', '%s payment pending'), number_format_i18n((int) $stats->pending)) : sc_t('dashboard_pages.none_pending', 'None pending payment')); ?></span>
        </div>
        <div class="w-kpi">
            <span class="w-kpi__label"><?php echo esc_html(sc_t('dashboard_pages.certificates', 'Certificates')); ?></span>
            <span class="w-kpi__value"><?php echo esc_html(number_format_i18n($certs)); ?></span>
            <span class="w-kpi__sub"><?php echo esc_html($workshop->enable_certificates ? sc_t('dashboard_pages.certificates_on', 'Attendees can claim theirs') : sc_t('dashboard_pages.certificates_off_short', 'Certificates are off')); ?></span>
        </div>
    </div>

    <div id="ws-attendees">
        <input type="hidden" data-w-filter="event_id" value="<?php echo (int) $workshop->event_id; ?>">
        <input type="hidden" data-w-filter="workshop_id" value="<?php echo (int) $workshop_id; ?>">
        <div class="w-tabs" role="tablist" data-w-tabs aria-label="<?php echo esc_attr(sc_t('dashboard_pages.attendees', 'Attendees')); ?>"></div>
        <div class="w-toolbar">
            <label class="w-search">
                <span class="sr-only"><?php echo esc_html(sc_t('dashboard_pages.search_attendees', 'Search name, email, phone or ticket code')); ?></span>
                <svg class="w-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true"><path d="M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14zM20 20l-3.5-3.5"/></svg>
                <input type="search" class="form-control" data-w-filter="search" placeholder="<?php echo esc_attr(sc_t('dashboard_pages.search_attendees', 'Search name, email, phone or ticket code')); ?>" autocomplete="off">
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
    var ticketViewUrl = <?php echo $js(home_url('/ticket-view/')); ?>;
    var L = <?php echo $js(array(
        'all'         => sc_t('dashboard_pages.all', 'All'),
        'in'          => sc_t('dashboard_pages.checked_in', 'Checked in'),
        'out'         => sc_t('dashboard_pages.not_checked_in', 'Not checked in'),
        'attendee'    => sc_t('dashboard_pages.attendee', 'Attendee'),
        'ticket'      => sc_t('dashboard_pages.ticket', 'Ticket'),
        'payment'     => sc_t('payments.payment', 'Payment'),
        'checkin'     => sc_t('dashboard_pages.check_in', 'Check-in'),
        'registered'  => sc_t('dashboard_pages.registered', 'Registered'),
        'notYet'      => sc_t('dashboard_pages.not_yet', 'Not yet'),
        'cancelled'   => sc_t('dashboard_pages.status_cancelled', 'Cancelled'),
        'pay'         => array(
            'success'  => sc_t('dashboard_pages.confirmed', 'Confirmed'),
            'pending'  => sc_t('dashboard_pages.pending', 'Pending'),
            'failed'   => sc_t('payments.failed', 'Failed'),
            'refunded' => sc_t('payments.refunded', 'Refunded'),
        ),
        'doCheckin'   => sc_t('dashboard_pages.check_in', 'Check in'),
        'undo'        => sc_t('dashboard_pages.undo_checkin', 'Undo check-in'),
        'edit'        => sc_t('dashboard_pages.edit', 'Edit'),
        'ticketView'  => sc_t('dashboard_pages.open_ticket', 'Open ticket'),
        'confirmUndo' => sc_t('dashboard_pages.confirm_undo_checkin', 'Undo %s’s check-in?'),
        'confirmBulk' => sc_t('dashboard_pages.confirm_bulk_checkin', 'Check in %d attendees?'),
        'search'      => sc_t('general.search', 'Search'),
        'emptyText'   => sc_t('dashboard_pages.no_workshop_attendees', 'Nobody has registered for this workshop yet.'),
        'failed'      => sc_t('errors.something_wrong', 'Something went wrong. Please try again.'),
    )); ?>;

    function post(data) { return $.ajax({ url: scDashboard.ajaxurl, type: 'POST', data: $.extend({ nonce: scDashboard.nonce }, data) }); }
    function after(res) {
        if (res.success) { showSuccess(res.data && res.data.message ? res.data.message : L.in); list.reload(true); }
        else { showError(res.data && res.data.message ? res.data.message : L.failed); }
    }
    var ICON = {
        check: 'M20 6 9 17l-5-5',
        undo: 'M3 7v6h6M21 17a9 9 0 0 0-15-6.7L3 13',
        pencil: 'M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z',
        ticket: 'M3 9V6a1 1 0 0 1 1-1h16a1 1 0 0 1 1 1v3a2 2 0 0 0 0 4v3a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-3a2 2 0 0 0 0-4z'
    };

    var list = WDList.create({
        root: document.getElementById('ws-attendees'),
        action: 'sc_get_attendees_paginated',
        rowsKey: 'attendees',
        filters: ['event_id', 'workshop_id', 'search'],
        fixedFilters: ['event_id', 'workshop_id'],
        perPage: 50,
        perPageOptions: [50, 100, 200],
        defaultSort: { orderby: 'name', order: 'asc' },
        tabs: [
            { key: 'all', label: L.all, countKey: 'all' },
            { key: 'in', label: L.in, params: { ticket_status: 'used' }, countKey: 'checked_in' },
            { key: 'out', label: L.out, params: { ticket_status: 'unused' }, countKey: 'not_checked_in' }
        ],
        emptyText: L.emptyText,
        columns: [
            {
                label: L.attendee, sort: 'name',
                render: function (a) {
                    return '<div class="w-stack"><a class="w-row-title" href="' + esc(dashboardUrl + 'attendee-edit?id=' + a.id) + '">' + esc(a.name) + '</a>' +
                        '<span class="w-sub w-ltr w-truncate">' + esc([a.email, a.phone].filter(Boolean).join(' · ')) + '</span></div>';
                }
            },
            {
                label: L.ticket,
                render: function (a) { return '<div class="w-stack"><span class="w-mono w-nowrap">' + esc(a.ticket_id) + '</span><span class="w-sub w-truncate">' + esc(a.ticket_name || '') + '</span></div>'; }
            },
            {
                label: L.payment,
                render: function (a) {
                    var tag = a.status === 'success' ? '' : ' w-tag--gold';
                    return '<span class="w-tag' + tag + '">' + esc(L.pay[a.status] || a.status) + '</span>' + (a.attendee_status === 'cancelled' ? ' <span class="w-tag w-tag--red">' + esc(L.cancelled) + '</span>' : '');
                }
            },
            {
                label: L.checkin, sort: 'checked_in_at',
                render: function (a) {
                    return a.checked_in
                        ? '<div class="w-stack"><span class="w-tag w-tag--teal">' + esc(L.in) + '</span><span class="w-sub w-ltr">' + esc(a.checkin_time) + '</span></div>'
                        : '<span class="text-muted">' + esc(L.notYet) + '</span>';
                }
            },
            {
                label: L.registered, sort: 'created_at', className: 'w-col-xl',
                render: function (a) { return '<span class="w-ltr w-nowrap">' + esc(String(a.created_at).slice(0, 10)) + '</span>'; }
            }
        ],
        rowMenu: function (a) {
            return [
                a.checked_in
                    ? { label: L.undo, icon: ICON.undo, onSelect: function () {
                        showConfirm(L.confirmUndo.replace('%s', a.name)).then(function (r) { if (r.isConfirmed) { post({ action: 'sc_undo_checkin_attendee', attendee_id: a.id }).done(after).fail(function () { showError(L.failed); }); } });
                    } }
                    : { label: L.doCheckin, icon: ICON.check, disabled: a.status !== 'success' || a.attendee_status === 'cancelled', onSelect: function () {
                        post({ action: 'sc_checkin_attendee', attendee_id: a.id }).done(after).fail(function () { showError(L.failed); });
                    } },
                { label: L.edit, icon: ICON.pencil, href: dashboardUrl + 'attendee-edit?id=' + a.id },
                { label: L.ticketView, icon: ICON.ticket, href: ticketViewUrl + '?attendee_id=' + a.id + '&ticket_code=' + encodeURIComponent(a.ticket_id) }
            ];
        },
        bulkActions: [
            { key: 'checkin', label: L.doCheckin, icon: ICON.check, run: function (ids) {
                showConfirm(L.confirmBulk.replace('%d', ids.length)).then(function (r) {
                    if (r.isConfirmed) { post({ action: 'sc_bulk_checkin_attendees', attendee_ids: ids }).done(after).fail(function () { showError(L.failed); }); }
                });
            } }
        ],
        chips: function (state) {
            return state.filters.search ? [{ label: L.search, value: state.filters.search, clear: function (l) { l.setFilter('search', ''); } }] : [];
        },
        onData: function (data) {
            if (data.counts) { $('[data-kpi="checked_in"]').text(WDList.num(data.counts.checked_in)); }
        }
    });
});
</script>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
