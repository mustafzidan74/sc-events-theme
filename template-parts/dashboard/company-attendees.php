<?php
/**
 * Company (exhibitor / B2B) registrations — list pattern over sc_get_company_attendees
 * (inc/admin-dashboard/company-dashboard.php).
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
            <h1><?php echo esc_html(sc_t('dashboard_pages.companies', 'Companies')); ?><span class="w-page-head__count" data-w-total></span></h1>
            <p class="w-page-head__sub"><?php echo esc_html(sc_t('dashboard_pages.companies_subtitle', 'Exhibitors and B2B registrations. Each company gets a badge with a QR code that the attendance scanner checks in.')); ?></p>
        </div>
        <div class="w-page-head__actions">
            <button type="button" class="btn btn-secondary" id="export-btn"><i class="fa fa-download" aria-hidden="true"></i> <?php echo esc_html(sc_t('dashboard_pages.export', 'Export')); ?></button>
            <a class="btn btn-secondary" id="scan-link" href="<?php echo esc_url($dashboard_url . 'scanner'); ?>"><i class="fa fa-qrcode" aria-hidden="true"></i> <?php echo esc_html(sc_t('dashboard_pages.open_scanner', 'Open scanner')); ?></a>
            <a class="btn btn-primary" id="add-link" href="<?php echo esc_url($dashboard_url . 'company-attendee-add'); ?>"><i class="fa fa-plus" aria-hidden="true"></i> <?php echo esc_html(sc_t('dashboard_pages.add_company', 'Add company')); ?></a>
        </div>
    </div>

    <div id="companies-list">
        <div class="w-tabs" role="tablist" data-w-tabs aria-label="<?php echo esc_attr(sc_t('dashboard_pages.companies', 'Companies')); ?>"></div>
        <div class="w-toolbar">
            <label class="w-search">
                <span class="sr-only"><?php echo esc_html(sc_t('dashboard_pages.search_companies', 'Search company, contact, email, phone, code or booth')); ?></span>
                <svg class="w-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true"><path d="M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14zM20 20l-3.5-3.5"/></svg>
                <input type="search" class="form-control" data-w-filter="search" placeholder="<?php echo esc_attr(sc_t('dashboard_pages.search_companies', 'Search company, contact, email, phone, code or booth')); ?>" autocomplete="off">
                <kbd class="w-search__kbd" aria-hidden="true">/</kbd>
            </label>
            <select class="form-control" data-w-filter="event_id" aria-label="<?php echo esc_attr(sc_t('dashboard_pages.all_events', 'All events')); ?>">
                <option value=""><?php echo esc_html(sc_t('dashboard_pages.all_events', 'All events')); ?></option>
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
        'all'        => sc_t('dashboard_pages.all', 'All'),
        'in'         => sc_t('dashboard_pages.checked_in', 'Checked in'),
        'out'        => sc_t('dashboard_pages.not_checked_in', 'Not checked in'),
        'unpaid'     => sc_t('dashboard_pages.payment_not_confirmed', 'Payment not confirmed'),
        'cancelled'  => sc_t('dashboard_pages.status_cancelled', 'Cancelled'),
        'company'    => sc_t('dashboard_pages.company', 'Company'),
        'contact'    => sc_t('dashboard_pages.contact', 'Contact'),
        'booth'      => sc_t('dashboard_pages.booth', 'Booth'),
        'checkin'    => sc_t('dashboard_pages.check_in', 'Check-in'),
        'event'      => sc_t('events.event', 'Event'),
        'notYet'     => sc_t('dashboard_pages.not_yet', 'Not yet'),
        'pay'        => array('success' => sc_t('dashboard_pages.confirmed', 'Confirmed'), 'pending' => sc_t('dashboard_pages.pending', 'Pending'), 'failed' => sc_t('payments.failed', 'Failed'), 'refunded' => sc_t('payments.refunded', 'Refunded')),
        'edit'       => sc_t('dashboard_pages.edit', 'Edit'),
        'badge'      => sc_t('dashboard_pages.open_badge', 'Open badge'),
        'email'      => sc_t('dashboard_pages.send_badge', 'Send the badge'),
        'doIn'       => sc_t('dashboard_pages.check_in', 'Check in'),
        'undo'       => sc_t('dashboard_pages.undo_checkin', 'Undo check-in'),
        'delete'     => sc_t('dashboard_pages.delete', 'Delete'),
        'emailed'    => sc_t('dashboard_pages.badge_emailed', 'Badge emailed %s'),
        'confirmDelete'    => sc_t('dashboard_pages.confirm_delete_companies', 'Delete %d companies? Their badges stop working. This cannot be undone.'),
        'confirmDeleteOne' => sc_t('dashboard_pages.confirm_delete_company', 'Delete %s? Their badge stops working. This cannot be undone.'),
        'confirmEmail'     => sc_t('dashboard_pages.confirm_send_badges', 'Send %d companies their badge? WhatsApp messages go out one by one.'),
        'confirmExport'    => sc_t('dashboard_pages.confirm_export_companies', 'Export %s companies matching the current view to CSV?'),
        'tooMany'    => sc_t('dashboard_pages.email_max_50', 'Select at most 50 to email at a time.'),
        'search'     => sc_t('general.search', 'Search'),
        'emptyText'  => sc_t('dashboard_pages.no_companies', 'No companies registered yet.'),
        'failed'     => sc_t('errors.something_wrong', 'Something went wrong. Please try again.'),
    )); ?>;

    function day(d) { return d ? new Date(String(d).replace(' ', 'T')).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' }) : ''; }
    function time(d) { return d ? String(d).slice(11, 16) : ''; }
    function bulk(op, ids) {
        return $.ajax({ url: scDashboard.ajaxurl, type: 'POST', data: { action: 'sc_company_bulk', nonce: scDashboard.nonce, op: op, ids: ids } })
            .done(function (res) {
                if (res.success) { (res.data.failed ? showWarning : showSuccess)(res.data.message); list.reload(true); }
                else { showError(res.data && res.data.message ? res.data.message : L.failed); }
            })
            .fail(function () { showError(L.failed); });
    }
    function askEmail(ids) {
        if (ids.length > 50) { showError(L.tooMany); return; }
        showConfirm(L.confirmEmail.replace('%d', ids.length)).then(function (r) { if (r.isConfirmed) { bulk('email', ids); } });
    }
    function confirmDelete(ids, name) {
        showDeleteConfirm(name ? L.confirmDeleteOne.replace('%s', name) : L.confirmDelete.replace('%d', ids.length)).then(function (r) { if (r.isConfirmed) { bulk('delete', ids); } });
    }
    var ICON = {
        pencil: 'M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z',
        badge: 'M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2M7 7h3v3H7zM14 7h3v3h-3zM7 14h3v3H7z',
        mail: 'M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2zM22 6l-10 7L2 6',
        check: 'M20 6 9 17l-5-5',
        undo: 'M3 7v6h6M21 17a9 9 0 0 0-15-6.7L3 13',
        trash: 'M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14'
    };

    var list = WDList.create({
        root: document.getElementById('companies-list'),
        action: 'sc_get_company_attendees',
        rowsKey: 'rows',
        filters: ['search', 'event_id'],
        perPage: 50,
        perPageOptions: [50, 100, 200],
        defaultSort: { orderby: 'created', order: 'desc' },
        tabs: [
            { key: 'all', label: L.all, params: { view: 'all' }, countKey: 'all' },
            { key: 'out', label: L.out, params: { view: 'out' }, countKey: 'out' },
            { key: 'in', label: L.in, params: { view: 'in' }, countKey: 'in' },
            { key: 'unpaid', label: L.unpaid, params: { view: 'unpaid' }, countKey: 'unpaid' },
            { key: 'cancelled', label: L.cancelled, params: { view: 'cancelled' }, countKey: 'cancelled' }
        ],
        emptyText: L.emptyText,
        onFiltersChange: function (f) {
            $('#add-link').attr('href', dashboardUrl + 'company-attendee-add' + (f.event_id ? '?event_id=' + encodeURIComponent(f.event_id) : ''));
            $('#scan-link').attr('href', dashboardUrl + 'scanner' + (f.event_id ? '?event_id=' + encodeURIComponent(f.event_id) : ''));
        },
        columns: [
            {
                label: L.company, sort: 'name',
                render: function (c) {
                    var logo = c.logo ? '<span class="w-logo-thumb"><img src="' + esc(c.logo) + '" alt=""></span>' : '<span class="w-logo-thumb w-logo-thumb--empty">' + esc(c.name.slice(0, 2).toUpperCase()) + '</span>';
                    return '<div class="w-person">' + logo + '<span class="w-person__text"><a class="w-row-title" href="' + esc(dashboardUrl + 'company-attendee-edit?id=' + c.id) + '">' + esc(c.name) + '</a>' +
                        '<span class="w-sub w-mono w-nowrap">' + esc(c.code) + '</span></span>' +
                        (c.status === 'cancelled' ? ' <span class="w-tag w-tag--red">' + esc(L.cancelled) + '</span>' : '') + '</div>';
                }
            },
            {
                label: L.contact,
                render: function (c) {
                    return '<div class="w-stack"><span class="w-truncate">' + esc(c.contact_name || c.email) + '</span><span class="w-sub w-ltr w-truncate">' + esc([c.contact_name ? c.email : '', c.phone].filter(Boolean).join(' · ')) + '</span></div>';
                }
            },
            {
                label: L.booth,
                render: function (c) {
                    if (!c.booth && !c.sponsorship) { return '<span class="text-muted">—</span>'; }
                    return '<div class="w-stack"><span class="w-nowrap">' + esc(c.booth || '—') + '</span>' + (c.sponsorship ? '<span class="w-sub">' + esc(c.sponsorship) + '</span>' : '') + '</div>';
                }
            },
            {
                label: L.event, className: 'w-col-xl',
                render: function (c) {
                    return '<div class="w-stack"><span class="w-truncate">' + esc(c.event_title) + '</span>' + (c.payment !== 'success' ? '<span class="w-tag w-tag--gold">' + esc(L.pay[c.payment] || c.payment) + '</span>' : '') + '</div>';
                }
            },
            {
                label: L.checkin, sort: 'checked_in_at',
                render: function (c) {
                    var sub = c.email_sent_at ? '<span class="w-sub">' + esc(L.emailed.replace('%s', day(c.email_sent_at))) + '</span>' : '';
                    return '<div class="w-stack">' + (c.checked_in ? '<span class="w-tag w-tag--teal w-ltr">' + esc(L.in + ' ' + day(c.checked_in_at) + ' ' + time(c.checked_in_at)) + '</span>' : '<span class="text-muted">' + esc(L.notYet) + '</span>') + sub + '</div>';
                }
            }
        ],
        rowMenu: function (c) {
            var active = c.status === 'active';
            return [
                { label: L.edit, icon: ICON.pencil, href: dashboardUrl + 'company-attendee-edit?id=' + c.id },
                { label: L.badge, icon: ICON.badge, href: c.badge_url },
                { label: L.email, icon: ICON.mail, disabled: !active, onSelect: function () { askEmail([c.id]); } },
                c.checked_in
                    ? { label: L.undo, icon: ICON.undo, onSelect: function () { bulk('undo', [c.id]); } }
                    : { label: L.doIn, icon: ICON.check, disabled: !active, onSelect: function () { bulk('check_in', [c.id]); } },
                { separator: true },
                { label: L.delete, icon: ICON.trash, danger: true, onSelect: function () { confirmDelete([c.id], c.name); } }
            ];
        },
        bulkActions: [
            { key: 'check_in', label: L.doIn, icon: ICON.check, run: function (ids) { bulk('check_in', ids); } },
            { key: 'email', label: L.email, icon: ICON.mail, run: function (ids) { askEmail(ids); } },
            { key: 'delete', label: L.delete, icon: ICON.trash, danger: true, run: function (ids) { confirmDelete(ids); } }
        ],
        chips: function (state) {
            var f = state.filters, chips = [];
            if (f.search) { chips.push({ label: L.search, value: f.search, clear: function (l) { l.setFilter('search', ''); } }); }
            if (f.event_id) { chips.push({ label: L.event, value: eventTitles[f.event_id] || ('#' + f.event_id), clear: function (l) { l.setFilter('event_id', ''); } }); }
            return chips;
        }
    });

    $('#export-btn').on('click', function () {
        var data = list.data();
        showConfirm(L.confirmExport.replace('%s', WDList.num(data ? data.total : 0))).then(function (r) {
            if (!r.isConfirmed) { return; }
            var p = list.params();
            p.action = 'sc_export_company_csv';
            p.nonce = scDashboard.nonce;
            window.location.href = scDashboard.ajaxurl + '?' + $.param(p);
        });
    });
});
</script>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
