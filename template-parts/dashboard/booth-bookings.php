<?php
/**
 * Booth bookings — list pattern over sc_booth_bookings_list, with confirm / check-in /
 * payment / cancel from the row menu. Handlers: inc/admin-dashboard/booths-dashboard.php.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

global $wpdb, $load_wd_list, $load_wd_form;
$load_wd_list = true;
$load_wd_form = true;

$events = $wpdb->get_results("SELECT id, title FROM {$wpdb->prefix}sc_events WHERE status IN ('publish', 'completed', 'draft') ORDER BY start_date DESC LIMIT 200");
$currency = get_option('sc_currency_code', 'EGP');
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
            <h1><?php echo esc_html(sc_t('booths.bookings', 'Booth bookings')); ?><span class="w-page-head__count" data-w-total></span></h1>
            <p class="w-page-head__sub"><?php echo esc_html(sc_t('booths.bookings_sub', 'Which company has which booth, what they owe and whether they have set up. Payments here are recorded by hand.')); ?></p>
        </div>
        <div class="w-page-head__actions">
            <a class="btn btn-primary" id="new-link" href="<?php echo esc_url($dashboard_url . 'booth-booking-create'); ?>"><i class="fa fa-plus" aria-hidden="true"></i> <?php echo esc_html(sc_t('booths.new_booking', 'New booking')); ?></a>
        </div>
    </div>

    <div id="bookings-list">
        <div class="w-tabs" role="tablist" data-w-tabs aria-label="<?php echo esc_attr(sc_t('booths.bookings', 'Booth bookings')); ?>"></div>
        <div class="w-toolbar">
            <label class="w-search">
                <span class="sr-only"><?php echo esc_html(sc_t('booths.search_bookings', 'Search reference, company or booth')); ?></span>
                <svg class="w-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true"><path d="M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14zM20 20l-3.5-3.5"/></svg>
                <input type="search" class="form-control" data-w-filter="search" placeholder="<?php echo esc_attr(sc_t('booths.search_bookings', 'Search reference, company or booth')); ?>" autocomplete="off">
                <kbd class="w-search__kbd" aria-hidden="true">/</kbd>
            </label>
            <select class="form-control" data-w-filter="event_id" aria-label="<?php echo esc_attr(sc_t('dashboard_pages.all_events', 'All events')); ?>">
                <option value=""><?php echo esc_html(sc_t('dashboard_pages.all_events', 'All events')); ?></option>
                <?php foreach ($events as $ev): ?>
                    <option value="<?php echo (int) $ev->id; ?>"><?php echo esc_html($ev->title); ?></option>
                <?php endforeach; ?>
            </select>
            <select class="form-control" data-w-filter="payment" aria-label="<?php echo esc_attr(sc_t('booths.payment', 'Payment')); ?>">
                <option value=""><?php echo esc_html(sc_t('booths.any_payment', 'Any payment')); ?></option>
                <option value="unpaid"><?php echo esc_html(sc_t('booths.unpaid', 'Unpaid')); ?></option>
                <option value="deposit_paid"><?php echo esc_html(sc_t('booths.deposit_paid', 'Deposit paid')); ?></option>
                <option value="fully_paid"><?php echo esc_html(sc_t('booths.paid', 'Paid')); ?></option>
                <option value="overdue"><?php echo esc_html(sc_t('booths.overdue', 'Overdue')); ?></option>
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

<div class="modal fade" id="payModal" tabindex="-1" role="dialog" aria-labelledby="pay-title">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="pay-form" novalidate autocomplete="off">
                <div class="modal-header">
                    <h5 class="modal-title" id="pay-title"><?php echo esc_html(sc_t('booths.record_payment', 'Record a payment')); ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="<?php echo esc_attr(sc_t('dashboard_pages.close', 'Close')); ?>"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <p class="w-issue__big" id="pay-summary"></p>
                    <div class="w-field">
                        <label for="pay-amount" class="w-field__label"><?php echo esc_html(sprintf(sc_t('booths.amount_received', 'Amount received (%s)'), $currency)); ?></label>
                        <input type="number" class="form-control" id="pay-amount" name="amount" min="0.01" step="0.01" inputmode="decimal" required>
                        <p class="w-field__help"><?php echo esc_html(sc_t('booths.payment_help', 'The first payment counts as the deposit; later ones go against the balance.')); ?></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo esc_html(sc_t('dashboard_pages.cancel', 'Cancel')); ?></button>
                    <button type="submit" class="btn btn-primary" id="pay-go"><?php echo esc_html(sc_t('booths.record', 'Record')); ?></button>
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
        'all'       => sc_t('dashboard_pages.all', 'All'),
        'views'     => array('pending' => sc_t('booths.pending', 'Pending'), 'confirmed' => sc_t('booths.confirmed', 'Confirmed'), 'active' => sc_t('booths.set_up', 'At the booth'), 'done' => sc_t('booths.completed', 'Completed'), 'cancelled' => sc_t('dashboard_pages.status_cancelled', 'Cancelled')),
        'status'    => array('pending' => sc_t('booths.pending', 'Pending'), 'confirmed' => sc_t('booths.confirmed', 'Confirmed'), 'active' => sc_t('booths.set_up', 'At the booth'), 'completed' => sc_t('booths.completed', 'Completed'), 'cancelled' => sc_t('dashboard_pages.status_cancelled', 'Cancelled'), 'no_show' => sc_t('booths.no_show', 'No-show')),
        'payment'   => array('pending' => sc_t('booths.unpaid', 'Unpaid'), 'deposit_paid' => sc_t('booths.deposit_paid', 'Deposit paid'), 'fully_paid' => sc_t('booths.paid', 'Paid'), 'overdue' => sc_t('booths.overdue', 'Overdue'), 'refunded' => sc_t('payments.refunded', 'Refunded'), 'cancelled' => sc_t('dashboard_pages.status_cancelled', 'Cancelled')),
        'booking'   => sc_t('booths.booking', 'Booking'),
        'booth'     => sc_t('booths.booth', 'Booth'),
        'amount'    => sc_t('booths.amount', 'Amount'),
        'state'     => sc_t('dashboard_pages.status', 'Status'),
        'event'     => sc_t('events.event', 'Event'),
        'dueX'      => sc_t('booths.due_x', '%s due'),
        'dueBy'     => sc_t('booths.due_by', 'by %s'),
        'paidInFull' => sc_t('booths.paid_in_full', 'Paid in full'),
        'edit'      => sc_t('dashboard_pages.edit', 'Open'),
        'confirm'   => sc_t('booths.confirm', 'Confirm'),
        'checkIn'   => sc_t('booths.check_in', 'Check in at the booth'),
        'checkOut'  => sc_t('booths.check_out', 'Hand the booth back'),
        'pay'       => sc_t('booths.record_payment', 'Record a payment'),
        'cancel'    => sc_t('booths.cancel_booking', 'Cancel booking'),
        'cancelAsk' => sc_t('booths.cancel_ask', 'Cancel booking %s? The booth becomes available again. Money already recorded stays on the booking.'),
        'reasonPh'  => sc_t('booths.reason_ph', 'Reason (optional)'),
        'paySummary' => sc_t('booths.pay_summary', '%1$s · %2$s still due'),
        'payment_f' => sc_t('booths.payment', 'Payment'),
        'search'    => sc_t('general.search', 'Search'),
        'emptyText' => sc_t('booths.no_bookings', 'No booth bookings yet.'),
        'failed'    => sc_t('errors.something_wrong', 'Something went wrong. Please try again.'),
    )); ?>;
    var ICON = {
        doc: 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zM14 2v6h6',
        check: 'M20 6 9 17l-5-5',
        in: 'M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3',
        out: 'M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9',
        cash: 'M2 6h20v12H2zM12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z',
        x: 'M18 6 6 18M6 6l12 12'
    };
    var STATUS_TAG = { pending: 'w-tag--gold', confirmed: 'w-tag--primary', active: 'w-tag--teal', completed: '', cancelled: 'w-tag--red', no_show: 'w-tag--red' };
    var PAY_TAG = { pending: '', deposit_paid: 'w-tag--gold', fully_paid: 'w-tag--teal', overdue: 'w-tag--red' };
    var money = function (n, c) { return Number(n || 0).toLocaleString('en-US', { maximumFractionDigits: 2 }) + ' ' + c; };
    var day = function (d) { return d ? new Date(String(d).slice(0, 10) + 'T00:00').toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : ''; };

    function act(op, row, extra) {
        return $.post(scDashboard.ajaxurl, $.extend({ action: 'sc_booth_booking_action', nonce: scDashboard.nonce, op: op, id: row.id }, extra || {}))
            .done(function (res) {
                if (!res.success) { showError(res.data && res.data.message || L.failed); return; }
                showSuccess(res.data.message);
                list.reload(true);
            })
            .fail(function () { showError(L.failed); });
    }
    function cancel(row) {
        Swal.fire({
            text: L.cancelAsk.replace('%s', row.ref), icon: 'warning', input: 'text', inputPlaceholder: L.reasonPh,
            showCancelButton: true, confirmButtonText: L.cancel, confirmButtonColor: '#b42318'
        }).then(function (r) { if (r.isConfirmed) { act('cancel', row, { reason: r.value || '' }); } });
    }
    var payRow = null;
    function pay(row) {
        payRow = row;
        $('#pay-form .w-field__error').remove();
        $('#pay-summary').text(L.paySummary.replace('%1$s', row.company.name + ' · ' + row.ref).replace('%2$s', money(row.due, row.currency)));
        $('#pay-amount').val(row.due ? row.due : '').attr('max', row.due);
        $('#payModal').modal('show');
    }
    $('#payModal').on('shown.bs.modal', function () { $('#pay-amount').trigger('focus').trigger('select'); });
    $('#pay-form').on('submit', function (e) {
        e.preventDefault();
        var btn = $('#pay-go').prop('disabled', true);
        $('#pay-form .w-field__error').remove();
        $.post(scDashboard.ajaxurl, { action: 'sc_booth_booking_action', nonce: scDashboard.nonce, op: 'payment', id: payRow.id, amount: $('#pay-amount').val() })
            .done(function (res) {
                if (!res.success) {
                    $('#pay-amount').closest('.w-field').append('<p class="w-field__error">' + esc(res.data && res.data.message || L.failed) + '</p>');
                    return;
                }
                $('#payModal').modal('hide');
                showSuccess(res.data.message);
                list.reload(true);
            })
            .fail(function () { showError(L.failed); })
            .always(function () { btn.prop('disabled', false); });
    });

    var list = WDList.create({
        root: document.getElementById('bookings-list'),
        action: 'sc_booth_bookings_list',
        rowsKey: 'rows',
        filters: ['search', 'event_id', 'payment'],
        perPage: 50,
        perPageOptions: [50, 100, 200],
        defaultSort: { orderby: 'created', order: 'desc' },
        tabs: [{ key: 'all', label: L.all, params: { view: 'all' }, countKey: 'all' }].concat(['pending', 'confirmed', 'active', 'done', 'cancelled'].map(function (k) {
            return { key: k, label: L.views[k], params: { view: k }, countKey: k };
        })),
        emptyText: L.emptyText,
        onFiltersChange: function (f) {
            $('#new-link').attr('href', dashboardUrl + 'booth-booking-create' + (f.event_id ? '?event_id=' + encodeURIComponent(f.event_id) : ''));
        },
        columns: [
            {
                label: L.booking, sort: 'created',
                render: function (r) {
                    return '<div class="w-stack"><a class="w-row-title" href="' + esc(dashboardUrl + 'booth-booking-edit?id=' + r.id) + '">' + esc(r.company.name || '—') + '</a>' +
                        '<span class="w-sub w-mono w-nowrap">' + esc(r.ref) + ' · ' + esc(day(r.created)) + '</span></div>';
                }
            },
            {
                label: L.booth, sort: 'booth',
                render: function (r) {
                    return '<div class="w-person"><span class="w-boothchip" style="--c:' + esc(r.booth.color) + '">' + esc(r.booth.number || '—') + '</span><span class="w-sub w-truncate">' + esc(r.booth.type) + '</span></div>';
                }
            },
            {
                label: L.amount, sort: 'total',
                render: function (r) {
                    var sub = r.due > 0
                        ? esc(L.dueX.replace('%s', money(r.due, r.currency))) + (r.due_date ? ' ' + esc(L.dueBy.replace('%s', day(r.due_date))) : '')
                        : (r.total > 0 ? esc(L.paidInFull) : '');
                    return '<div class="w-stack w-nowrap"><span>' + esc(money(r.total, r.currency)) + '</span><span class="w-sub">' + sub + '</span></div>';
                }
            },
            {
                label: L.event, className: 'w-col-xl',
                render: function (r) { return '<span class="w-truncate">' + esc(r.event) + '</span>'; }
            },
            {
                label: L.state,
                render: function (r) {
                    return '<div class="w-stack w-nowrap"><span class="w-tag ' + (STATUS_TAG[r.status] || '') + '">' + esc(L.status[r.status] || r.status) + '</span>' +
                        '<span class="w-tag ' + (PAY_TAG[r.payment] || '') + '">' + esc(L.payment[r.payment] || r.payment) + '</span></div>';
                }
            }
        ],
        rowMenu: function (r) {
            var live = ['pending', 'confirmed', 'active'].indexOf(r.status) > -1;
            var items = [{ label: L.edit, icon: ICON.doc, href: dashboardUrl + 'booth-booking-edit?id=' + r.id }];
            if (r.status === 'pending') { items.push({ label: L.confirm, icon: ICON.check, onSelect: function () { act('confirm', r); } }); }
            if (r.status === 'pending' || r.status === 'confirmed') { items.push({ label: L.checkIn, icon: ICON.in, onSelect: function () { act('check_in', r); } }); }
            if (r.status === 'active') { items.push({ label: L.checkOut, icon: ICON.out, onSelect: function () { act('check_out', r); } }); }
            items.push({ label: L.pay, icon: ICON.cash, disabled: !(r.due > 0), onSelect: function () { pay(r); } });
            if (live) {
                items.push({ separator: true });
                items.push({ label: L.cancel, icon: ICON.x, danger: true, onSelect: function () { cancel(r); } });
            }
            return items;
        },
        chips: function (state) {
            var f = state.filters, chips = [];
            if (f.search) { chips.push({ label: L.search, value: f.search, clear: function (l) { l.setFilter('search', ''); } }); }
            if (f.event_id) { chips.push({ label: L.event, value: eventTitles[f.event_id] || ('#' + f.event_id), clear: function (l) { l.setFilter('event_id', ''); } }); }
            if (f.payment) { chips.push({ label: L.payment_f, value: $('[data-w-filter="payment"] option:selected').text(), clear: function (l) { l.setFilter('payment', ''); } }); }
            return chips;
        }
    });
});
</script>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
