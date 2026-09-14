<?php
/**
 * Support inbox — contact form messages on the list pattern (sc_support_list / sc_support_bulk
 * in inc/admin-dashboard/support-ajax-handlers.php). A message opens in a reading dialog with
 * reply links: email and, when there is a phone number, WhatsApp.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

global $load_wd_list, $load_wd_form;
$load_wd_list = true;
$load_wd_form = true;

$dashboard_url = home_url('/event-manager-dashboard/');
$platform_name = get_option('sc_platform_name', get_bloginfo('name'));
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
            <h1><?php echo esc_html(sc_t('nav.support', 'Support')); ?><span class="w-page-head__count" data-w-total></span></h1>
            <p class="w-page-head__sub"><?php echo esc_html(sc_t('support.sub', 'Messages sent from the contact page. Reply by email or WhatsApp, then mark the message as replied or resolved so the team knows it is handled.')); ?></p>
        </div>
    </div>

    <div id="support-list">
        <div class="w-tabs" role="tablist" data-w-tabs aria-label="<?php echo esc_attr(sc_t('nav.support', 'Support')); ?>"></div>
        <div class="w-toolbar">
            <label class="w-search">
                <span class="sr-only"><?php echo esc_html(sc_t('support.search', 'Search name, email, phone or message')); ?></span>
                <svg class="w-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true"><path d="M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14zM20 20l-3.5-3.5"/></svg>
                <input type="search" class="form-control" data-w-filter="search" placeholder="<?php echo esc_attr(sc_t('support.search', 'Search name, email, phone or message')); ?>" autocomplete="off">
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

<div class="modal fade" id="msgModal" tabindex="-1" role="dialog" aria-labelledby="msg-subject">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <div class="w-stack">
                    <h5 class="modal-title" id="msg-subject"></h5>
                    <span class="w-sub" id="msg-when"></span>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="<?php echo esc_attr(sc_t('dashboard_pages.close', 'Close')); ?>"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="w-msg__from">
                    <span class="w-person__avatar" id="msg-avatar" data-tone="0"></span>
                    <div class="w-stack">
                        <strong id="msg-name"></strong>
                        <span class="w-sub w-ltr"><a id="msg-email" href="#"></a><span id="msg-phone-wrap"> · <a id="msg-phone" href="#"></a></span></span>
                        <span class="w-sub" id="msg-known"></span>
                    </div>
                </div>
                <div class="w-msg__body" id="msg-body" dir="auto"></div>
                <div class="w-aside-actions mt-3">
                    <a class="btn btn-primary btn-sm" id="msg-reply-email" href="#" target="_blank" rel="noopener"><?php echo esc_html(sc_t('support.reply_email', 'Reply by email')); ?></a>
                    <a class="btn btn-secondary btn-sm" id="msg-reply-wa" href="#" target="_blank" rel="noopener"><?php echo esc_html(sc_t('support.reply_whatsapp', 'Reply on WhatsApp')); ?></a>
                    <a class="btn btn-secondary btn-sm" id="msg-attendee" href="#"><?php echo esc_html(sc_t('support.see_registrations', 'See their registrations')); ?></a>
                </div>
            </div>
            <div class="modal-footer w-msg__foot">
                <button type="button" class="btn btn-link w-tpl__delete mr-auto" data-msg-op="delete"><?php echo esc_html(sc_t('dashboard_pages.delete', 'Delete')); ?></button>
                <button type="button" class="btn btn-secondary" data-msg-op="new"><?php echo esc_html(sc_t('support.mark_new', 'Mark as new')); ?></button>
                <button type="button" class="btn btn-secondary" data-msg-op="contacted"><?php echo esc_html(sc_t('support.mark_replied', 'Mark as replied')); ?></button>
                <button type="button" class="btn btn-primary" data-msg-op="resolved"><?php echo esc_html(sc_t('support.mark_resolved', 'Mark as resolved')); ?></button>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(function ($) {
    'use strict';

    var esc = WDList.esc;
    var dashboardUrl = <?php echo $js($dashboard_url); ?>;
    var platform = <?php echo $js($platform_name); ?>;
    var L = <?php echo $js(array(
        'views'     => array('new' => sc_t('support.new', 'New'), 'contacted' => sc_t('support.replied', 'Replied'), 'resolved' => sc_t('support.resolved', 'Resolved'), 'all' => sc_t('dashboard_pages.all', 'All')),
        'from'      => sc_t('support.from', 'From'),
        'message'   => sc_t('support.message', 'Message'),
        'received'  => sc_t('support.received', 'Received'),
        'state'     => sc_t('dashboard_pages.status', 'Status'),
        'open'      => sc_t('support.read', 'Read'),
        'regs'      => sc_t('support.n_registrations', '%s registrations'),
        'oneReg'    => sc_t('support.one_registration', '1 registration'),
        'certs'     => sc_t('support.n_certificates', '%s certificates'),
        'oneCert'   => sc_t('support.one_certificate', '1 certificate'),
        'noReg'     => sc_t('support.not_registered', 'No registrations under this email'),
        'markReplied' => sc_t('support.mark_replied', 'Mark as replied'),
        'markResolved' => sc_t('support.mark_resolved', 'Mark as resolved'),
        'markNew'   => sc_t('support.mark_new', 'Mark as new'),
        'delete'    => sc_t('dashboard_pages.delete', 'Delete'),
        'confirmDelete'    => sc_t('support.confirm_delete', 'Delete %d messages? This cannot be undone.'),
        'confirmDeleteOne' => sc_t('support.confirm_delete_one', 'Delete this message from %s? This cannot be undone.'),
        'replyGreeting' => sc_t('support.reply_greeting', 'Hello %s,'),
        'search'    => sc_t('general.search', 'Search'),
        'emptyText' => sc_t('support.empty', 'No messages here.'),
        'failed'    => sc_t('errors.something_wrong', 'Something went wrong. Please try again.'),
    )); ?>;
    var ICON = {
        eye: 'M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8zM12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z',
        reply: 'M9 17 4 12l5-5M20 18v-2a4 4 0 0 0-4-4H4',
        check: 'M20 6 9 17l-5-5',
        dot: 'M12 13a1 1 0 1 0 0-2 1 1 0 0 0 0 2z',
        trash: 'M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14'
    };
    var TAG = { new: 'w-tag--gold', contacted: 'w-tag--primary', resolved: 'w-tag--teal' };

    function tone(s) { var h = 0; for (var i = 0; i < s.length; i++) { h = (h * 31 + s.charCodeAt(i)) % 4; } return h; }
    function initials(name) { return String(name || '?').trim().split(/\s+/).slice(0, 2).map(function (w) { return w.charAt(0).toUpperCase(); }).join('') || '?'; }
    function when(d) {
        if (!d) { return ''; }
        var dt = new Date(String(d).replace(' ', 'T'));
        var days = Math.floor((Date.now() - dt.getTime()) / 86400000);
        var date = dt.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: dt.getFullYear() === new Date().getFullYear() ? undefined : 'numeric' });
        return days < 1 ? String(d).slice(11, 16) : date;
    }
    // Egyptian numbers are usually written 01xxxxxxxxx; WhatsApp wants 201xxxxxxxxx.
    function waNumber(phone) {
        var d = String(phone || '').replace(/[^\d]/g, '');
        if (/^00/.test(d)) { d = d.slice(2); }
        if (/^01\d{9}$/.test(d)) { d = '2' + d; }
        return d.length >= 10 ? d : '';
    }
    function known(r) {
        if (!r.registrations) { return L.noReg; }
        return [r.registrations === 1 ? L.oneReg : L.regs.replace('%s', r.registrations), r.certificates ? (r.certificates === 1 ? L.oneCert : L.certs.replace('%s', r.certificates)) : ''].filter(Boolean).join(' · ');
    }

    function bulk(op, ids) {
        return $.post(scDashboard.ajaxurl, { action: 'sc_support_bulk', nonce: scDashboard.nonce, op: op, ids: ids })
            .done(function (res) {
                if (!res.success) { showError(res.data && res.data.message || L.failed); return; }
                if (window.toastr) { toastr.success(res.data.message); }
                list.reload();
            })
            .fail(function () { showError(L.failed); });
    }
    function askDelete(ids, name) {
        showDeleteConfirm(name ? L.confirmDeleteOne.replace('%s', name) : L.confirmDelete.replace('%d', ids.length)).then(function (r) {
            if (r.isConfirmed) { $('#msgModal').modal('hide'); bulk('delete', ids); }
        });
    }

    var current = null;
    function openMessage(r) {
        current = r;
        $('#msg-subject').text(r.subject);
        $('#msg-when').text(new Date(String(r.created).replace(' ', 'T')).toLocaleString('en-GB', { dateStyle: 'medium', timeStyle: 'short' }));
        $('#msg-avatar').text(initials(r.name)).attr('data-tone', tone(r.name));
        $('#msg-name').text(r.name);
        $('#msg-email').text(r.email).attr('href', 'mailto:' + encodeURIComponent(r.email).replace(/%40/g, '@'));
        $('#msg-phone-wrap').prop('hidden', !r.phone);
        var wa = waNumber(r.phone);
        $('#msg-phone').text(r.phone).attr('href', 'tel:' + String(r.phone).replace(/[^\d+]/g, ''));
        $('#msg-known').text(known(r));
        $('#msg-body').text(r.message);
        var greeting = L.replyGreeting.replace('%s', r.name) + '\n\n';
        var quoted = '\n\n---\n' + r.message.split('\n').map(function (line) { return '> ' + line; }).join('\n');
        $('#msg-reply-email').attr('href', 'mailto:' + encodeURIComponent(r.email).replace(/%40/g, '@') + '?subject=' + encodeURIComponent('Re: ' + r.subject) + '&body=' + encodeURIComponent(greeting + quoted.slice(0, 1500)));
        $('#msg-reply-wa').prop('hidden', !wa).attr('href', wa ? 'https://wa.me/' + wa + '?text=' + encodeURIComponent(greeting + '(' + platform + ') ' + r.subject) : '#');
        $('#msg-attendee').prop('hidden', !r.registrations).attr('href', dashboardUrl + 'attendees?search=' + encodeURIComponent(r.email));
        $('[data-msg-op="new"]').prop('hidden', r.status === 'new');
        $('[data-msg-op="contacted"]').prop('hidden', r.status === 'contacted');
        $('[data-msg-op="resolved"]').prop('hidden', r.status === 'resolved');
        $('#msgModal').modal('show');
    }
    $('#msgModal').on('click', '[data-msg-op]', function () {
        var op = this.getAttribute('data-msg-op');
        if (!current) { return; }
        if (op === 'delete') { askDelete([current.id], current.name); return; }
        $('#msgModal').modal('hide');
        bulk(op, [current.id]);
    });
    // Replying is the usual next step: offer to mark it after the reply link is used.
    $('#msg-reply-email, #msg-reply-wa').on('click', function () {
        if (current && current.status === 'new') {
            setTimeout(function () { $('[data-msg-op="contacted"]').addClass('w-pulse').trigger('focus'); }, 400);
        }
    });

    var list = WDList.create({
        root: document.getElementById('support-list'),
        action: 'sc_support_list',
        rowsKey: 'rows',
        filters: ['search'],
        perPage: 50,
        perPageOptions: [50, 100, 200],
        defaultSort: { orderby: 'created', order: 'desc' },
        tabs: ['new', 'contacted', 'resolved', 'all'].map(function (k) { return { key: k, label: L.views[k], params: { view: k }, countKey: k }; }),
        emptyText: L.emptyText,
        columns: [
            {
                label: L.from,
                render: function (r) {
                    return '<div class="w-person"><span class="w-person__avatar" data-tone="' + tone(r.name) + '">' + esc(initials(r.name)) + '</span><span class="w-person__text">' +
                        '<button type="button" class="w-row-title w-linkbtn" data-open="' + r.id + '">' + esc(r.name) + '</button>' +
                        '<span class="w-sub w-ltr w-truncate">' + esc(r.email) + (r.phone ? ' · ' + esc(r.phone) : '') + '</span></span></div>';
                }
            },
            {
                label: L.message,
                render: function (r) {
                    return '<div class="w-stack w-msg__cell"><span class="w-truncate' + (r.status === 'new' ? ' w-strong' : '') + '" dir="auto">' + esc(r.subject) + '</span>' +
                        '<span class="w-sub w-msg__excerpt" dir="auto">' + esc(r.message.replace(/\s+/g, ' ').slice(0, 140)) + '</span>' +
                        (r.registrations ? '<span class="w-sub">' + esc(known(r)) + '</span>' : '') + '</div>';
                }
            },
            {
                label: L.received, sort: 'created',
                render: function (r) { return '<span class="w-nowrap" title="' + esc(r.created) + '">' + esc(when(r.created)) + '</span>'; }
            },
            {
                label: L.state,
                render: function (r) { return '<span class="w-tag ' + (TAG[r.status] || '') + '">' + esc(L.views[r.status] || r.status) + '</span>'; }
            }
        ],
        rowMenu: function (r) {
            return [
                { label: L.open, icon: ICON.eye, onSelect: function () { openMessage(r); } },
                r.status !== 'contacted' ? { label: L.markReplied, icon: ICON.reply, onSelect: function () { bulk('contacted', [r.id]); } } : null,
                r.status !== 'resolved' ? { label: L.markResolved, icon: ICON.check, onSelect: function () { bulk('resolved', [r.id]); } } : null,
                r.status !== 'new' ? { label: L.markNew, icon: ICON.dot, onSelect: function () { bulk('new', [r.id]); } } : null,
                { separator: true },
                { label: L.delete, icon: ICON.trash, danger: true, onSelect: function () { askDelete([r.id], r.name); } }
            ];
        },
        bulkActions: [
            { key: 'contacted', label: L.markReplied, icon: ICON.reply, run: function (ids) { bulk('contacted', ids); } },
            { key: 'resolved', label: L.markResolved, icon: ICON.check, run: function (ids) { bulk('resolved', ids); } },
            { key: 'delete', label: L.delete, icon: ICON.trash, danger: true, run: function (ids) { askDelete(ids); } }
        ],
        chips: function (state) {
            return state.filters.search ? [{ label: L.search, value: state.filters.search, clear: function (l) { l.setFilter('search', ''); } }] : [];
        }
    });

    $('#support-list').on('click', '[data-open]', function () {
        var id = +this.getAttribute('data-open');
        var r = list.rows().filter(function (x) { return x.id === id; })[0];
        if (r) { openMessage(r); }
    });
});
</script>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
