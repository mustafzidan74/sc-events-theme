<?php
/**
 * Customers — attendee accounts, list pattern over sc_get_customers_paginated
 * (inc/admin-dashboard/customers-dashboard.php).
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
$currency = get_option('sc_currency_code', 'EGP');
$js = function ($value) {
    return wp_json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
};
$t = array(
    'title'      => sc_t('dashboard_pages.customers', 'Customers'),
    'subtitle'   => sc_t('dashboard_pages.customers_subtitle', 'Everyone with an account on the site. Registrations are matched by account and by email.'),
    'search'     => sc_t('dashboard_pages.search_customers', 'Search name, email or phone'),
    'all_events' => sc_t('dashboard_pages.any_event', 'Any event'),
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
            <button type="button" class="btn btn-secondary" id="export-btn"><i class="fa fa-download" aria-hidden="true"></i> <?php echo esc_html(sc_t('dashboard_pages.export', 'Export')); ?></button>
        </div>
    </div>

    <div id="customers-list">
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
                    <option value="<?php echo (int) $ev->id; ?>"><?php echo esc_html(sprintf(sc_t('dashboard_pages.registered_for_x', 'Registered for %s'), $ev->title)); ?></option>
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

<div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-labelledby="edit-title">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="edit-form" novalidate autocomplete="off">
                <div class="modal-header">
                    <h5 class="modal-title" id="edit-title"><?php echo esc_html(sc_t('dashboard_pages.edit_account', 'Edit account')); ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="<?php echo esc_attr(sc_t('dashboard_pages.close', 'Close')); ?>"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="w-fields">
                        <div class="w-fields w-fields--2">
                            <div class="w-field"><label for="ed-first" class="w-field__label"><?php echo esc_html(sc_t('dashboard_pages.first_name', 'First name')); ?></label><input type="text" class="form-control" id="ed-first"></div>
                            <div class="w-field"><label for="ed-last" class="w-field__label"><?php echo esc_html(sc_t('dashboard_pages.last_name', 'Last name')); ?></label><input type="text" class="form-control" id="ed-last"></div>
                        </div>
                        <div class="w-field"><label for="ed-email" class="w-field__label"><?php echo esc_html(sc_t('general.email', 'Email')); ?></label><input type="email" class="form-control w-ltr" id="ed-email"><p class="w-field__help"><?php echo esc_html(sc_t('dashboard_pages.email_login_help', 'They sign in with this email. Existing registrations keep the email they were made with.')); ?></p></div>
                        <div class="w-field"><label for="ed-phone" class="w-field__label"><?php echo esc_html(sc_t('general.phone', 'Phone')); ?></label><input type="tel" class="form-control w-ltr" id="ed-phone"></div>
                        <div class="w-field"><label for="ed-pass" class="w-field__label"><?php echo esc_html(sc_t('dashboard_pages.new_password', 'New password')); ?></label><input type="text" class="form-control w-ltr" id="ed-pass" autocomplete="new-password"><p class="w-field__help"><?php echo esc_html(sc_t('dashboard_pages.new_password_help', 'Leave empty to keep their password. At least 8 characters; tell them yourself.')); ?></p></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo esc_html(sc_t('dashboard_pages.cancel', 'Cancel')); ?></button>
                    <button type="submit" class="btn btn-primary" id="edit-go"><?php echo esc_html(sc_t('dashboard_pages.save', 'Save')); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="emailModal" tabindex="-1" role="dialog" aria-labelledby="email-title">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form id="email-form" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="email-title"></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="<?php echo esc_attr(sc_t('dashboard_pages.close', 'Close')); ?>"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="w-fields">
                        <div class="w-field">
                            <label for="email-subject" class="w-field__label"><?php echo esc_html(sc_t('dashboard_pages.subject', 'Subject')); ?></label>
                            <input type="text" class="form-control" id="email-subject" maxlength="200" required>
                        </div>
                        <div class="w-field">
                            <label for="email-message" class="w-field__label"><?php echo esc_html(sc_t('dashboard_pages.message', 'Message')); ?></label>
                            <textarea class="form-control" id="email-message" rows="8" required></textarea>
                            <p class="w-field__help"><?php echo esc_html(sc_t('dashboard_pages.email_placeholders_help', 'Write {name} or {email} to fill in each person’s details.')); ?></p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo esc_html(sc_t('dashboard_pages.cancel', 'Cancel')); ?></button>
                    <button type="submit" class="btn btn-primary" id="email-go"><?php echo esc_html(sc_t('dashboard_pages.send', 'Send')); ?></button>
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
    var currency = <?php echo $js($currency); ?>;
    var eventTitles = <?php echo $js(array_reduce($events, function ($c, $e) { $c[(int) $e->id] = $e->title; return $c; }, array())); ?>;
    var L = <?php echo $js(array(
        'all'         => sc_t('dashboard_pages.all', 'All'),
        'registered'  => sc_t('dashboard_pages.have_registered', 'Have registered'),
        'never'       => sc_t('dashboard_pages.never_registered', 'Never registered'),
        'customer'    => sc_t('dashboard_pages.customer', 'Customer'),
        'regs'        => sc_t('dashboard_pages.registrations', 'Registrations'),
        'lastEvent'   => sc_t('dashboard_pages.latest_registration', 'Latest registration'),
        'attended'    => sc_t('dashboard_pages.checked_in', 'Checked in'),
        'paid'        => sc_t('dashboard_pages.paid', 'Paid'),
        'joined'      => sc_t('dashboard_pages.joined', 'Joined'),
        'nEvents'     => sc_t('dashboard_pages.n_events', '%s events'),
        'oneEvent'    => sc_t('dashboard_pages.one_event', '1 event'),
        'none'        => sc_t('dashboard_pages.none', 'None'),
        'editAccount' => sc_t('dashboard_pages.edit_account', 'Edit account'),
        'seeRegs'     => sc_t('dashboard_pages.see_registrations', 'See registrations'),
        'email'       => sc_t('dashboard_pages.send_email', 'Send email'),
        'emailTo'     => sc_t('dashboard_pages.email_to', 'Email %s'),
        'emailMany'   => sc_t('dashboard_pages.email_n_people', 'Email %d people'),
        'tooMany'     => sc_t('dashboard_pages.email_max_200', 'Select at most 200 people to email at a time.'),
        'required'    => sc_t('dashboard_pages.subject_message_required', 'Write a subject and a message.'),
        'delete'      => sc_t('dashboard_pages.delete_account', 'Delete account'),
        'cantDelete'  => sc_t('dashboard_pages.cant_delete_registered', 'Accounts with registrations can’t be deleted'),
        'confirmDel'  => sc_t('dashboard_pages.confirm_delete_accounts', 'Delete %d accounts? Only accounts that never registered are deleted; the rest are kept. This cannot be undone.'),
        'confirmDelOne' => sc_t('dashboard_pages.confirm_delete_account', 'Delete %s’s account? They never registered for anything. This cannot be undone.'),
        'confirmExport' => sc_t('dashboard_pages.confirm_export_customers', 'Export %s customers matching the current view to CSV?'),
        'search'      => sc_t('general.search', 'Search'),
        'event'       => sc_t('events.event', 'Event'),
        'emptyText'   => sc_t('dashboard_pages.no_customers', 'No accounts yet.'),
        'failed'      => sc_t('errors.something_wrong', 'Something went wrong. Please try again.'),
    )); ?>;

    function post(data) { return $.ajax({ url: scDashboard.ajaxurl, type: 'POST', data: $.extend({ nonce: scDashboard.nonce }, data) }); }
    function day(d) { return d ? new Date(String(d).replace(' ', 'T')).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : ''; }
    function money(n) { return Number(n).toLocaleString('en-US', { maximumFractionDigits: 0 }) + ' ' + currency; }

    var emailIds = [];
    function openEmail(ids, name) {
        if (ids.length > 200) { showError(L.tooMany); return; }
        emailIds = ids;
        $('#email-title').text(name ? L.emailTo.replace('%s', name) : L.emailMany.replace('%d', ids.length));
        $('#emailModal').modal('show');
    }
    $('#email-form').on('submit', function (e) {
        e.preventDefault();
        var subject = $.trim($('#email-subject').val()), message = $.trim($('#email-message').val());
        if (!subject || !message) { showError(L.required); return; }
        var $b = $('#email-go').prop('disabled', true);
        post({ action: 'sc_bulk_email_customers', customer_ids: emailIds, subject: subject, message: message.replace(/\n/g, '<br>') })
            .done(function (res) {
                if (res.success) { $('#emailModal').modal('hide'); document.getElementById('email-form').reset(); showSuccess(res.data.message); }
                else { showError(res.data && res.data.message || L.failed); }
            })
            .fail(function () { showError(L.failed); })
            .always(function () { $b.prop('disabled', false); });
    });

    var editId = 0;
    function openEdit(id) {
        post({ action: 'sc_get_customer_account', customer_id: id }).done(function (res) {
            if (!res.success) { showError(res.data && res.data.message || L.failed); return; }
            editId = id;
            $('#ed-first').val(res.data.first_name); $('#ed-last').val(res.data.last_name);
            $('#ed-email').val(res.data.email); $('#ed-phone').val(res.data.phone || ''); $('#ed-pass').val('');
            $('#editModal').modal('show');
        }).fail(function () { showError(L.failed); });
    }
    $('#edit-form').on('submit', function (e) {
        e.preventDefault();
        var $b = $('#edit-go').prop('disabled', true);
        post({ action: 'sc_update_customer', customer_id: editId, first_name: $('#ed-first').val(), last_name: $('#ed-last').val(), email: $('#ed-email').val(), phone: $('#ed-phone').val(), new_password: $('#ed-pass').val() })
            .done(function (res) {
                if (res.success) { $('#editModal').modal('hide'); showSuccess(res.data.message); list.reload(true); }
                else { showError(res.data && res.data.message || L.failed); }
            })
            .fail(function () { showError(L.failed); })
            .always(function () { $b.prop('disabled', false); });
    });

    function remove(ids, name) {
        showDeleteConfirm(name ? L.confirmDelOne.replace('%s', name) : L.confirmDel.replace('%d', ids.length)).then(function (r) {
            if (!r.isConfirmed) { return; }
            post({ action: 'sc_bulk_delete_customers', customer_ids: ids }).done(function (res) {
                if (res.success) { (res.data.kept ? showWarning : showSuccess)(res.data.message); list.reload(); }
                else { showError(res.data && res.data.message || L.failed); }
            }).fail(function () { showError(L.failed); });
        });
    }

    var ICON = {
        list: 'M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01',
        pencil: 'M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z',
        mail: 'M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2zM22 6l-10 7L2 6',
        trash: 'M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14'
    };

    var list = WDList.create({
        root: document.getElementById('customers-list'),
        action: 'sc_get_customers_paginated',
        rowsKey: 'rows',
        filters: ['search', 'event_id'],
        perPage: 50,
        perPageOptions: [50, 100, 200],
        defaultSort: { orderby: 'joined', order: 'desc' },
        tabs: [
            { key: 'all', label: L.all, params: { view: 'all' }, countKey: 'all' },
            { key: 'registered', label: L.registered, params: { view: 'registered' }, countKey: 'registered' },
            { key: 'never', label: L.never, params: { view: 'never' }, countKey: 'never' }
        ],
        emptyText: L.emptyText,
        columns: [
            {
                label: L.customer, sort: 'name',
                render: function (c) {
                    return '<div class="w-stack"><span class="w-row-title">' + esc(c.name) + '</span><span class="w-sub w-ltr w-truncate">' + esc([c.email, c.phone].filter(Boolean).join(' · ')) + '</span></div>';
                }
            },
            {
                label: L.regs,
                render: function (c) {
                    if (!c.registrations) { return '<span class="text-muted">' + esc(L.none) + '</span>'; }
                    return '<div class="w-stack"><span class="w-num">' + WDList.num(c.registrations) + '</span><span class="w-sub">' + esc(c.events === 1 ? L.oneEvent : L.nEvents.replace('%s', c.events)) + '</span></div>';
                }
            },
            {
                label: L.lastEvent,
                render: function (c) { return c.last_event ? '<div class="w-stack"><span class="w-truncate">' + esc(c.last_event) + '</span><span class="w-sub">' + esc(day(c.last_at)) + '</span></div>' : '<span class="text-muted">—</span>'; }
            },
            {
                label: L.attended, className: 'w-col-xl',
                render: function (c) { return c.registrations ? '<span class="w-num">' + WDList.num(c.checked_in) + '</span>' : '<span class="text-muted">—</span>'; }
            },
            {
                label: L.paid, className: 'w-col-xl',
                render: function (c) { return c.paid ? '<span class="w-num w-nowrap">' + esc(money(c.paid)) + '</span>' : '<span class="text-muted">—</span>'; }
            },
            {
                label: L.joined, sort: 'joined',
                render: function (c) { return '<span class="w-nowrap">' + esc(day(c.joined)) + '</span>'; }
            }
        ],
        rowMenu: function (c) {
            return [
                { label: L.editAccount, icon: ICON.pencil, onSelect: function () { openEdit(c.id); } },
                { label: L.seeRegs, icon: ICON.list, href: dashboardUrl + 'attendees?search=' + encodeURIComponent(c.email), disabled: !c.registrations },
                { label: L.email, icon: ICON.mail, onSelect: function () { openEmail([c.id], c.name); } },
                { separator: true },
                { label: c.registrations ? L.cantDelete : L.delete, icon: ICON.trash, danger: true, disabled: c.registrations > 0, onSelect: function () { remove([c.id], c.name); } }
            ];
        },
        bulkActions: [
            { key: 'email', label: L.email, icon: ICON.mail, run: function (ids) { openEmail(ids); } },
            { key: 'delete', label: L.delete, icon: ICON.trash, danger: true, run: function (ids) { remove(ids); } }
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
            p.action = 'sc_export_customers_csv';
            p.nonce = scDashboard.nonce;
            window.location.href = scDashboard.ajaxurl + '?' + $.param(p);
        });
    });
});
</script>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
