<?php
/**
 * Certificates — list pattern over sc_get_certificates_paginated
 * (inc/admin-dashboard/certificates-dashboard.php).
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

$events = $wpdb->get_results("SELECT e.id, e.title FROM {$wpdb->prefix}sc_events e WHERE e.status IN ('publish', 'completed', 'draft') OR EXISTS (SELECT 1 FROM {$wpdb->prefix}sc_certificates c WHERE c.event_id = e.id) ORDER BY e.start_date DESC LIMIT 200");
$dashboard_url = home_url('/event-manager-dashboard/');
$js = function ($value) {
    return wp_json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
};
$t = array(
    'title'      => sc_t('nav.certificates', 'Certificates'),
    'subtitle'   => sc_t('dashboard_pages.certificates_subtitle', 'Every certificate issued — by you here, or claimed by attendees from My Account. Each one has a QR code that opens its verification page.'),
    'search'     => sc_t('dashboard_pages.search_certificates', 'Search name, email or certificate number'),
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
            <a class="btn btn-secondary" href="<?php echo esc_url($dashboard_url . 'certificate-templates'); ?>"><?php echo esc_html(sc_t('dashboard_pages.templates', 'Templates')); ?></a>
            <?php if (function_exists('sc_wabot_candidates') && sc_wabot_candidates(null, 'campaign')): ?>
            <a class="btn btn-secondary" id="wa-send-link" href="<?php echo esc_url($dashboard_url . 'whatsapp-send?source=certificates'); ?>"><?php echo esc_html(sc_t('whatsapp.send_certificates', 'Send on WhatsApp')); ?></a>
            <?php endif; ?>
            <a class="btn btn-secondary" href="<?php echo esc_url(home_url('/certificate-verify/')); ?>" target="_blank" rel="noopener"><i class="fa fa-external-link" aria-hidden="true"></i> <?php echo esc_html(sc_t('dashboard_pages.verification_page', 'Verification page')); ?></a>
            <a class="btn btn-primary" id="issue-link" href="<?php echo esc_url($dashboard_url . 'certificate-issue'); ?>"><i class="fa fa-plus" aria-hidden="true"></i> <?php echo esc_html(sc_t('dashboard_pages.issue_certificates', 'Issue certificates')); ?></a>
        </div>
    </div>

    <div id="certificates-list">
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

<div class="modal fade" id="revokeModal" tabindex="-1" role="dialog" aria-labelledby="revoke-title">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="revoke-form" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="revoke-title"></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="<?php echo esc_attr(sc_t('dashboard_pages.close', 'Close')); ?>"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <p class="w-field__help mt-0"><?php echo esc_html(sc_t('dashboard_pages.revoke_help', 'The PDF they already have keeps working as a file, but its QR code and verification page will say the certificate is no longer valid. You can reinstate it later.')); ?></p>
                    <label for="revoke-reason" class="w-field__label"><?php echo esc_html(sc_t('dashboard_pages.reason_internal', 'Reason (only visible in the dashboard)')); ?></label>
                    <textarea class="form-control" id="revoke-reason" rows="2"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo esc_html(sc_t('dashboard_pages.cancel', 'Cancel')); ?></button>
                    <button type="submit" class="btn btn-danger" id="revoke-go"><?php echo esc_html(sc_t('dashboard_pages.revoke', 'Revoke')); ?></button>
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
    var eventTitles = <?php echo $js(array_reduce($events, function ($c, $e) { $c[(int) $e->id] = $e->title; return $c; }, array())); ?>;
    var L = <?php echo $js(array(
        'all'          => sc_t('dashboard_pages.all', 'All'),
        'issued'       => sc_t('dashboard_pages.not_downloaded_yet', 'Not downloaded yet'),
        'downloaded'   => sc_t('dashboard_pages.downloaded', 'Downloaded'),
        'revoked'      => sc_t('dashboard_pages.revoked', 'Revoked'),
        'unsent'       => sc_t('dashboard_pages.not_sent_or_downloaded', 'Never sent or downloaded'),
        'attendee'     => sc_t('dashboard_pages.attendee', 'Attendee'),
        'certificate'  => sc_t('dashboard_pages.certificate', 'Certificate'),
        'event'        => sc_t('events.event', 'Event'),
        'issued_col'   => sc_t('dashboard_pages.issued', 'Issued'),
        'downloads'    => sc_t('dashboard_pages.downloads', 'Downloads'),
        'status'       => sc_t('dashboard_pages.status', 'Status'),
        'byAttendee'   => sc_t('dashboard_pages.claimed_by_attendee', 'Claimed by attendee'),
        'byStaff'      => sc_t('dashboard_pages.issued_by_staff', 'Issued from dashboard'),
        'never'        => sc_t('dashboard_pages.not_yet', 'Not yet'),
        'emailed'      => sc_t('dashboard_pages.sent_on', 'Sent %s'),
        'lastOn'       => sc_t('dashboard_pages.first_on', 'First %s'),
        'valid'        => sc_t('dashboard_pages.valid', 'Valid'),
        'deletedAtt'   => sc_t('dashboard_pages.registration_deleted', 'Registration deleted'),
        'download'     => sc_t('dashboard_pages.download_pdf', 'Download PDF'),
        'verify'       => sc_t('dashboard_pages.open_verification', 'Open verification page'),
        'copyLink'     => sc_t('dashboard_pages.copy_verification_link', 'Copy verification link'),
        'copied'       => sc_t('dashboard_pages.link_copied', 'Link copied.'),
        'email'        => sc_t('dashboard_pages.send_certificate', 'Send the certificate'),
        'attendeeLink' => sc_t('dashboard_pages.open_registration', 'Open registration'),
        'revoke'       => sc_t('dashboard_pages.revoke', 'Revoke'),
        'reinstate'    => sc_t('dashboard_pages.reinstate', 'Reinstate'),
        'revokeOne'    => sc_t('dashboard_pages.revoke_certificate_of', 'Revoke %s’s certificate?'),
        'revokeMany'   => sc_t('dashboard_pages.revoke_n_certificates', 'Revoke %d certificates?'),
        'confirmEmail' => sc_t('dashboard_pages.confirm_send_certificates', 'Send %d people their certificate? WhatsApp gets the PDF (one by one), and email when it is on.'),
        'tooMany'      => sc_t('dashboard_pages.send_max_50', 'Select at most 50 people to send to at a time.'),
        'search'       => sc_t('general.search', 'Search'),
        'emptyText'    => sc_t('dashboard_pages.no_certificates', 'No certificates yet. Issue them for an event, or turn on certificates for an event so attendees can claim theirs.'),
        'failed'       => sc_t('errors.something_wrong', 'Something went wrong. Please try again.'),
    )); ?>;

    function post(data) { return $.ajax({ url: scDashboard.ajaxurl, type: 'POST', data: $.extend({ nonce: scDashboard.nonce }, data) }); }
    function bulk(op, ids, extra) {
        return post($.extend({ action: 'sc_certificates_bulk', op: op, ids: ids }, extra || {}))
            .done(function (res) {
                if (res.success) { (res.data.failed ? showWarning : showSuccess)(res.data.message); list.reload(); }
                else { showError(res.data && res.data.message || L.failed); }
            })
            .fail(function () { showError(L.failed); });
    }
    function day(d) { return d ? new Date(String(d).replace(' ', 'T')).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : ''; }
    function copy(text) {
        var done = function () { showSuccess(L.copied); };
        if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(text).then(done); return; }
        var ta = document.createElement('textarea'); ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
        document.body.appendChild(ta); ta.select(); try { document.execCommand('copy'); } catch (x) { /* ignore */ } document.body.removeChild(ta); done();
    }
    var revokeIds = [];
    function askRevoke(ids, name) {
        revokeIds = ids;
        $('#revoke-title').text(name ? L.revokeOne.replace('%s', name) : L.revokeMany.replace('%d', ids.length));
        $('#revoke-reason').val('');
        $('#revokeModal').modal('show');
    }
    function askEmail(ids) {
        if (ids.length > 50) { showError(L.tooMany); return; }
        showConfirm(L.confirmEmail.replace('%d', ids.length)).then(function (r) { if (r.isConfirmed) { bulk('email', ids); } });
    }

    var ICON = {
        download: 'M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3',
        shield: 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10zM9 12l2 2 4-4',
        link: 'M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7',
        mail: 'M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2zM22 6l-10 7L2 6',
        user: 'M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z',
        ban: 'M18.4 5.6 5.6 18.4M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20z',
        undo: 'M3 7v6h6M21 17a9 9 0 0 0-15-6.7L3 13'
    };
    var TAG = {
        issued: '<span class="w-tag">' + esc(L.valid) + '</span>',
        downloaded: '<span class="w-tag w-tag--teal">' + esc(L.downloaded) + '</span>',
        revoked: '<span class="w-tag w-tag--red">' + esc(L.revoked) + '</span>'
    };

    var list = WDList.create({
        root: document.getElementById('certificates-list'),
        action: 'sc_get_certificates_paginated',
        rowsKey: 'rows',
        filters: ['search', 'event_id'],
        perPage: 50,
        perPageOptions: [50, 100, 200],
        defaultSort: { orderby: 'issued', order: 'desc' },
        tabs: [
            { key: 'all', label: L.all, params: { view: 'all' }, countKey: 'all' },
            { key: 'issued', label: L.issued, params: { view: 'issued' }, countKey: 'issued' },
            { key: 'downloaded', label: L.downloaded, params: { view: 'downloaded' }, countKey: 'downloaded' },
            { key: 'unsent', label: L.unsent, params: { view: 'unsent' }, countKey: 'unsent' },
            { key: 'revoked', label: L.revoked, params: { view: 'revoked' }, countKey: 'revoked' }
        ],
        emptyText: L.emptyText,
        onFiltersChange: function (f) {
            $('#issue-link').attr('href', dashboardUrl + 'certificate-issue' + (f.event_id ? '?event_id=' + encodeURIComponent(f.event_id) : ''));
            $('#wa-send-link').attr('href', dashboardUrl + 'whatsapp-send?source=certificates' + (f.event_id ? '&event_id=' + encodeURIComponent(f.event_id) : ''));
        },
        columns: [
            {
                label: L.attendee, sort: 'name',
                render: function (c) {
                    var name = c.attendee_live
                        ? '<a class="w-row-title" href="' + esc(dashboardUrl + 'attendee-edit?id=' + c.attendee_id) + '">' + esc(c.name) + '</a>'
                        : '<span class="w-row-title">' + esc(c.name) + '</span>';
                    return '<div class="w-stack">' + name + '<span class="w-sub w-ltr w-truncate">' + esc(c.email || (c.attendee_live ? '' : L.deletedAtt)) + '</span></div>';
                }
            },
            {
                label: L.event,
                render: function (c) {
                    return '<div class="w-stack"><span class="w-truncate">' + esc(c.event_title) + '</span>' + (c.workshop ? '<span class="w-sub w-truncate">' + esc(c.workshop) + '</span>' : '') + '</div>';
                }
            },
            {
                label: L.certificate, className: 'w-col-xl',
                render: function (c) { return '<div class="w-stack"><span class="w-mono w-nowrap">' + esc(c.number) + '</span><span class="w-sub w-truncate">' + esc(c.template) + '</span></div>'; }
            },
            {
                label: L.issued_col, sort: 'issued',
                render: function (c) { return '<div class="w-stack"><span class="w-nowrap">' + esc(day(c.issued_at)) + '</span><span class="w-sub">' + esc(c.self_claimed ? L.byAttendee : L.byStaff) + '</span></div>'; }
            },
            {
                label: L.downloads, sort: 'downloads',
                render: function (c) {
                    var sub = c.downloads ? L.lastOn.replace('%s', day(c.downloaded_at)) : (c.email_sent_at ? L.emailed.replace('%s', day(c.email_sent_at)) : L.never);
                    return '<div class="w-stack"><span class="w-num">' + WDList.num(c.downloads) + '</span><span class="w-sub w-nowrap">' + esc(sub) + '</span></div>';
                }
            },
            {
                label: L.status,
                render: function (c) { return (TAG[c.status] || '') + (c.status === 'revoked' && c.revoke_reason ? '<span class="w-sub w-truncate d-block" title="' + esc(c.revoke_reason) + '">' + esc(c.revoke_reason) + '</span>' : ''); }
            }
        ],
        rowMenu: function (c) {
            var live = c.status !== 'revoked';
            return [
                { label: L.download, icon: ICON.download, href: c.download_url, disabled: !live },
                { label: L.verify, icon: ICON.shield, onSelect: function () { window.open(c.verify_url, '_blank', 'noopener'); } },
                { label: L.copyLink, icon: ICON.link, onSelect: function () { copy(c.verify_url); } },
                { label: L.email, icon: ICON.mail, disabled: !live, onSelect: function () { askEmail([c.id]); } },
                { label: L.attendeeLink, icon: ICON.user, href: dashboardUrl + 'attendee-edit?id=' + c.attendee_id, disabled: !c.attendee_live },
                { separator: true },
                live
                    ? { label: L.revoke, icon: ICON.ban, danger: true, onSelect: function () { askRevoke([c.id], c.name); } }
                    : { label: L.reinstate, icon: ICON.undo, onSelect: function () { bulk('reinstate', [c.id]); } }
            ];
        },
        bulkActions: [
            { key: 'email', label: L.email, icon: ICON.mail, run: function (ids) { askEmail(ids); } },
            { key: 'reinstate', label: L.reinstate, icon: ICON.undo, run: function (ids) { bulk('reinstate', ids); } },
            { key: 'revoke', label: L.revoke, icon: ICON.ban, danger: true, run: function (ids) { askRevoke(ids); } }
        ],
        chips: function (state) {
            var f = state.filters, chips = [];
            if (f.search) { chips.push({ label: L.search, value: f.search, clear: function (l) { l.setFilter('search', ''); } }); }
            if (f.event_id) { chips.push({ label: L.event, value: eventTitles[f.event_id] || ('#' + f.event_id), clear: function (l) { l.setFilter('event_id', ''); } }); }
            return chips;
        }
    });

    $('#revoke-form').on('submit', function (e) {
        e.preventDefault();
        var $b = $('#revoke-go').prop('disabled', true);
        bulk('revoke', revokeIds, { reason: $('#revoke-reason').val() }).always(function () { $b.prop('disabled', false); $('#revokeModal').modal('hide'); });
    });
});
</script>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
