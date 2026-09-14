<?php
/**
 * Booths — list pattern over sc_booths_list, with "Add booths" numbering dialog.
 * Handlers: inc/admin-dashboard/booths-dashboard.php.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

global $wpdb, $load_wd_list, $load_wd_form, $load_wd_overview;
$load_wd_list = true;
$load_wd_overview = true;
$load_wd_form = true;
$p = $wpdb->prefix;

$events = $wpdb->get_results($wpdb->prepare(
    "SELECT e.id, e.title, COALESCE(e.end_date, e.start_date) >= %s AS current, (SELECT COUNT(*) FROM {$p}sc_booths b WHERE b.event_id = e.id) AS booths
     FROM {$p}sc_events e WHERE e.status IN ('publish', 'completed', 'draft') ORDER BY e.start_date DESC LIMIT 200",
    current_time('Y-m-d')
));
$default_event = 0;
foreach ($events as $ev) {
    if ((int) $ev->booths) {
        $default_event = (int) $ev->id;
        break;
    }
}
foreach ($default_event ? array() : $events as $ev) {
    if ((int) $ev->current) {
        $default_event = (int) $ev->id;
    }
}
if (!$default_event && $events) {
    $default_event = (int) $events[0]->id;
}

$types_by_event = array();
foreach ($wpdb->get_results("SELECT id, event_id, name, width_meters, depth_meters, base_price, color FROM {$p}sc_booth_types ORDER BY sort_order, name") as $t) {
    $types_by_event[(int) $t->event_id][] = array('id' => (int) $t->id, 'name' => $t->name, 'w' => (float) $t->width_meters, 'd' => (float) $t->depth_meters, 'price' => (float) $t->base_price, 'color' => $t->color);
}
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
            <h1><?php echo esc_html(sc_t('booths.all_booths', 'Booths')); ?><span class="w-page-head__count" data-w-total></span></h1>
            <p class="w-page-head__sub"><?php echo esc_html(sc_t('booths.booths_sub', 'Every booth on the floor. A booth shows as reserved, booked or occupied from its booking; close a booth to keep it off sale.')); ?></p>
        </div>
        <div class="w-page-head__actions">
            <a class="btn btn-secondary" id="plan-link" href="<?php echo esc_url($dashboard_url . 'booth-floor-plan'); ?>"><?php echo esc_html(sc_t('booths.floor_plan', 'Floor plan')); ?></a>
            <a class="btn btn-secondary" id="one-link" href="<?php echo esc_url($dashboard_url . 'booth-create'); ?>"><?php echo esc_html(sc_t('booths.add_one', 'Add one booth')); ?></a>
            <button type="button" class="btn btn-primary" id="add-booths"><i class="fa fa-plus" aria-hidden="true"></i> <?php echo esc_html(sc_t('booths.add_booths', 'Add booths')); ?></button>
        </div>
    </div>

    <div class="w-kpis" id="booth-kpis">
        <div class="w-kpi"><span class="w-kpi__label"><?php echo esc_html(sc_t('booths.booths', 'Booths')); ?></span><span class="w-kpi__value" data-kpi="total">—</span><span class="w-kpi__sub" data-kpi="area"></span></div>
        <div class="w-kpi"><span class="w-kpi__label"><?php echo esc_html(sc_t('booths.available', 'Available')); ?></span><span class="w-kpi__value" data-kpi="available">—</span><span class="w-kpi__sub" data-kpi="taken"></span></div>
        <div class="w-kpi"><span class="w-kpi__label"><?php echo esc_html(sc_t('booths.booked_value', 'Booked value')); ?></span><span class="w-kpi__value" data-kpi="booked">—</span><span class="w-kpi__sub"><?php echo esc_html(sc_t('booths.excl_cancelled', 'Bookings not cancelled')); ?></span></div>
        <div class="w-kpi"><span class="w-kpi__label"><?php echo esc_html(sc_t('booths.collected', 'Collected')); ?></span><span class="w-kpi__value" data-kpi="collected">—</span><span class="w-kpi__sub" data-kpi="collected-sub"></span></div>
    </div>

    <div id="booths-list">
        <div class="w-tabs" role="tablist" data-w-tabs aria-label="<?php echo esc_attr(sc_t('booths.booths', 'Booths')); ?>"></div>
        <div class="w-toolbar">
            <select class="form-control w-badges__event" data-w-filter="event_id" aria-label="<?php echo esc_attr(sc_t('events.event', 'Event')); ?>">
                <?php foreach ($events as $ev): ?>
                    <option value="<?php echo (int) $ev->id; ?>"><?php echo esc_html($ev->title); ?></option>
                <?php endforeach; ?>
            </select>
            <label class="w-search">
                <span class="sr-only"><?php echo esc_html(sc_t('booths.search', 'Search booth number, name or company')); ?></span>
                <svg class="w-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true"><path d="M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14zM20 20l-3.5-3.5"/></svg>
                <input type="search" class="form-control" data-w-filter="search" placeholder="<?php echo esc_attr(sc_t('booths.search', 'Search booth number, name or company')); ?>" autocomplete="off">
                <kbd class="w-search__kbd" aria-hidden="true">/</kbd>
            </label>
            <select class="form-control" data-w-filter="type_id" id="type-filter" aria-label="<?php echo esc_attr(sc_t('booths.booth_type', 'Booth type')); ?>"></select>
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

<div class="modal fade" id="addModal" tabindex="-1" role="dialog" aria-labelledby="add-title">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="add-form" novalidate autocomplete="off">
                <div class="modal-header">
                    <h5 class="modal-title" id="add-title"><?php echo esc_html(sc_t('booths.add_booths', 'Add booths')); ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="<?php echo esc_attr(sc_t('dashboard_pages.close', 'Close')); ?>"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="w-fields">
                        <div class="w-field" id="add-no-types" hidden>
                            <p class="mb-2"><?php echo esc_html(sc_t('booths.need_type_first', 'This event has no booth types yet. Booths take their size and price from a type.')); ?></p>
                            <a class="btn btn-sm btn-primary" id="add-type-link" href="<?php echo esc_url($dashboard_url . 'booth-type-create'); ?>"><?php echo esc_html(sc_t('booths.add_type', 'Add booth type')); ?></a>
                        </div>
                        <div class="w-field" data-needs-type>
                            <label for="add-type" class="w-field__label"><?php echo esc_html(sc_t('booths.booth_type', 'Booth type')); ?></label>
                            <select class="form-control" id="add-type" name="booth_type_id"></select>
                        </div>
                        <div class="w-fields w-fields--3" data-needs-type>
                            <div class="w-field">
                                <label for="add-prefix" class="w-field__label"><?php echo esc_html(sc_t('booths.prefix', 'Prefix')); ?></label>
                                <input type="text" class="form-control w-ltr" id="add-prefix" name="prefix" value="A" maxlength="10">
                            </div>
                            <div class="w-field">
                                <label for="add-start" class="w-field__label"><?php echo esc_html(sc_t('booths.first_number', 'First number')); ?></label>
                                <input type="number" class="form-control" id="add-start" name="start" value="1" min="0" inputmode="numeric">
                            </div>
                            <div class="w-field">
                                <label for="add-count" class="w-field__label"><?php echo esc_html(sc_t('booths.how_many', 'How many')); ?></label>
                                <input type="number" class="form-control" id="add-count" name="count" value="10" min="1" max="200" inputmode="numeric">
                            </div>
                        </div>
                        <div class="w-fields w-fields--2" data-needs-type>
                            <div class="w-field">
                                <label for="add-floor" class="w-field__label"><?php echo esc_html(sc_t('booths.floor', 'Floor')); ?></label>
                                <input type="number" class="form-control" id="add-floor" name="floor_level" value="1" min="0" max="20" inputmode="numeric">
                            </div>
                            <label class="w-check-line align-self-end mb-2"><input type="checkbox" name="pad" value="1" checked> <?php echo esc_html(sc_t('booths.pad', 'Same length numbers (A01, A02…)')); ?></label>
                        </div>
                        <p class="w-issue__big w-ltr mb-0" id="add-preview" data-needs-type></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo esc_html(sc_t('dashboard_pages.cancel', 'Cancel')); ?></button>
                    <button type="submit" class="btn btn-primary" id="add-go" data-needs-type><?php echo esc_html(sc_t('booths.add_booths', 'Add booths')); ?></button>
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
    var typesByEvent = <?php echo $js((object) $types_by_event); ?>;
    var L = <?php echo $js(array(
        'all'         => sc_t('dashboard_pages.all', 'All'),
        'available'   => sc_t('booths.available', 'Available'),
        'taken'       => sc_t('booths.taken', 'Reserved or booked'),
        'unavailable' => sc_t('booths.closed', 'Closed'),
        'status'      => array('available' => sc_t('booths.available', 'Available'), 'reserved' => sc_t('booths.reserved', 'Reserved'), 'booked' => sc_t('booths.booked', 'Booked'), 'occupied' => sc_t('booths.occupied', 'Occupied'), 'unavailable' => sc_t('booths.closed', 'Closed')),
        'payment'     => array('pending' => sc_t('booths.unpaid', 'Unpaid'), 'deposit_paid' => sc_t('booths.deposit_paid', 'Deposit paid'), 'fully_paid' => sc_t('booths.paid', 'Paid'), 'overdue' => sc_t('booths.overdue', 'Overdue'), 'refunded' => sc_t('payments.refunded', 'Refunded'), 'cancelled' => sc_t('dashboard_pages.status_cancelled', 'Cancelled')),
        'allTypes'    => sc_t('booths.all_types', 'All types'),
        'booth'       => sc_t('booths.booth', 'Booth'),
        'type'        => sc_t('booths.booth_type', 'Type'),
        'price'       => sc_t('booths.price', 'Price'),
        'company'     => sc_t('dashboard_pages.company', 'Company'),
        'state'       => sc_t('dashboard_pages.status', 'Status'),
        'custom'      => sc_t('booths.custom_price', 'Custom price'),
        'featured'    => sc_t('booths.featured', 'Featured'),
        'floor'       => sc_t('booths.floor_n', 'Floor %s'),
        'amen'        => array('power' => sc_t('booths.power', 'Power'), 'water' => sc_t('booths.water', 'Water'), 'wifi' => sc_t('booths.wifi', 'Wi-Fi')),
        'edit'        => sc_t('dashboard_pages.edit', 'Edit'),
        'book'        => sc_t('booths.book', 'Book for a company'),
        'openBooking' => sc_t('booths.open_booking', 'Open booking %s'),
        'close'       => sc_t('booths.close_booth', 'Close (keep off sale)'),
        'open'        => sc_t('booths.open_booth', 'Open for booking'),
        'delete'      => sc_t('dashboard_pages.delete', 'Delete'),
        'areaX'       => sc_t('booths.area_total', '%s m² in total'),
        'takenX'      => sc_t('booths.taken_x', '%s reserved or booked'),
        'ofBooked'    => sc_t('booths.of_booked_pct', '%s of booked value'),
        'confirmDelete'    => sc_t('booths.confirm_delete_booths', 'Delete %d booths? Booths with bookings are kept. This cannot be undone.'),
        'confirmDeleteOne' => sc_t('booths.confirm_delete_booth', 'Delete booth %s? This cannot be undone.'),
        'preview'     => sc_t('booths.preview_numbers', '%1$s to %2$s'),
        'search'      => sc_t('general.search', 'Search'),
        'emptyText'   => sc_t('booths.no_booths', 'No booths for this event yet. Use Add booths to number a row of them in one go.'),
        'failed'      => sc_t('errors.something_wrong', 'Something went wrong. Please try again.'),
    )); ?>;
    var ICON = {
        pencil: 'M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z',
        cart: 'M9 21a1 1 0 1 0 0-2 1 1 0 0 0 0 2zM20 21a1 1 0 1 0 0-2 1 1 0 0 0 0 2zM1 1h4l2.7 13.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6L23 6H6',
        doc: 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zM14 2v6h6',
        lock: 'M5 11h14v10H5zM8 11V7a4 4 0 0 1 8 0v4',
        unlock: 'M5 11h14v10H5zM8 11V7a4 4 0 0 1 7.8-1.2',
        trash: 'M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14'
    };
    var TAG = { available: 'w-tag--teal', reserved: 'w-tag--gold', booked: 'w-tag--primary', occupied: 'w-tag--primary', unavailable: '' };
    var money = function (n) { return Number(n || 0).toLocaleString('en-US', { maximumFractionDigits: 2 }) + ' ' + currency; };
    var m = function (n) { return Number(n).toLocaleString('en-US', { maximumFractionDigits: 2 }); };

    // Open on the event that already has booths.
    (function () {
        var q = new URLSearchParams(location.search);
        if (!q.get('event_id')) {
            q.set('event_id', <?php echo (int) $default_event; ?>);
            history.replaceState(null, '', location.pathname + '?' + q.toString());
        }
    })();

    function fillTypes(eventId) {
        var list = typesByEvent[eventId] || [];
        var sel = $('#type-filter');
        var keep = sel.val() || new URLSearchParams(location.search).get('type_id') || '';
        sel.html('<option value="">' + esc(L.allTypes) + '</option>' + list.map(function (t) { return '<option value="' + t.id + '">' + esc(t.name) + '</option>'; }).join(''));
        sel.val(list.some(function (t) { return String(t.id) === String(keep); }) ? keep : '');
        $('#add-type').html(list.map(function (t) { return '<option value="' + t.id + '">' + esc(t.name + ' · ' + m(t.w) + ' × ' + m(t.d) + ' m · ' + money(t.price)) + '</option>'; }).join(''));
        $('#add-no-types').prop('hidden', list.length > 0);
        $('[data-needs-type]').prop('hidden', !list.length);
        $('#add-type-link').attr('href', dashboardUrl + 'booth-type-create?event_id=' + eventId);
    }
    fillTypes(new URLSearchParams(location.search).get('event_id'));

    function bulk(op, ids) {
        return $.post(scDashboard.ajaxurl, { action: 'sc_booths_bulk', nonce: scDashboard.nonce, op: op, ids: ids })
            .done(function (res) {
                if (!res.success) { showError(res.data && res.data.message || L.failed); return; }
                (res.data.failed ? showWarning : showSuccess)(res.data.message);
                list.reload();
            })
            .fail(function () { showError(L.failed); });
    }

    var list = WDList.create({
        root: document.getElementById('booths-list'),
        action: 'sc_booths_list',
        rowsKey: 'rows',
        filters: ['event_id', 'search', 'type_id'],
        fixedFilters: ['event_id'],
        perPage: 50,
        perPageOptions: [50, 100, 200],
        defaultSort: { orderby: 'number', order: 'asc' },
        tabs: [
            { key: 'all', label: L.all, params: { view: 'all' }, countKey: 'all' },
            { key: 'available', label: L.available, params: { view: 'available' }, countKey: 'available' },
            { key: 'taken', label: L.taken, params: { view: 'taken' }, countKey: 'taken' },
            { key: 'unavailable', label: L.unavailable, params: { view: 'unavailable' }, countKey: 'unavailable' }
        ],
        emptyText: L.emptyText,
        onFiltersChange: function (f) {
            if (f.event_id && String(f.event_id) !== String($('#type-filter').data('event'))) {
                $('#type-filter').data('event', f.event_id);
                fillTypes(f.event_id);
            }
            var ev = f.event_id ? '?event_id=' + encodeURIComponent(f.event_id) : '';
            $('#plan-link').attr('href', dashboardUrl + 'booth-floor-plan' + ev);
            $('#one-link').attr('href', dashboardUrl + 'booth-create' + ev);
        },
        onData: function (data) {
            var s = data.stats;
            if (!s) { return; }
            $('[data-kpi="total"]').text(WDList.num(s.total));
            $('[data-kpi="area"]').text(s.area ? L.areaX.replace('%s', m(s.area)) : '');
            $('[data-kpi="available"]').text(WDList.num(s.available));
            $('[data-kpi="taken"]').text(L.takenX.replace('%s', WDList.num(s.taken)));
            $('[data-kpi="booked"]').text(money(s.booked));
            $('[data-kpi="collected"]').text(money(s.collected));
            $('[data-kpi="collected-sub"]').text(s.booked ? L.ofBooked.replace('%s', Math.round(s.collected / s.booked * 100) + '%') : '');
        },
        columns: [
            {
                label: L.booth, sort: 'number',
                render: function (b) {
                    return '<div class="w-person"><span class="w-boothchip" style="--c:' + esc(b.color) + '">' + esc(b.number) + '</span><span class="w-person__text">' +
                        '<a class="w-row-title" href="' + esc(dashboardUrl + 'booth-edit?id=' + b.id) + '">' + esc(b.name || b.type) + '</a>' +
                        '<span class="w-sub w-truncate">' + esc([b.name ? b.type : '', b.size ? m(b.size.w) + ' × ' + m(b.size.d) + ' m' : '', L.floor.replace('%s', b.floor)].filter(Boolean).join(' · ')) + '</span></span>' +
                        (b.featured ? ' <span class="w-tag w-tag--gold">' + esc(L.featured) + '</span>' : '') + '</div>';
                }
            },
            {
                label: L.price, sort: 'price',
                render: function (b) {
                    return '<div class="w-stack w-nowrap"><span>' + esc(money(b.price)) + '</span>' + (b.custom_price ? '<span class="w-sub">' + esc(L.custom) + '</span>' : '') + '</div>';
                }
            },
            {
                label: L.company,
                render: function (b) {
                    if (!b.company) { return '<span class="text-muted">—</span>'; }
                    return '<div class="w-stack"><a class="w-truncate" href="' + esc(dashboardUrl + 'company-attendee-edit?id=' + b.company.id) + '">' + esc(b.company.name) + '</a>' +
                        (b.booking ? '<a class="w-sub w-mono" href="' + esc(dashboardUrl + 'booth-booking-edit?id=' + b.booking.id) + '">' + esc(b.booking.ref) + ' · ' + esc(L.payment[b.booking.payment] || b.booking.payment) + '</a>' : '') + '</div>';
                }
            },
            {
                label: L.state, sort: 'status',
                render: function (b) {
                    return '<div class="w-stack w-nowrap"><span class="w-tag ' + (TAG[b.status] || '') + '">' + esc(L.status[b.status] || b.status) + '</span>' +
                        (b.amenities.length ? '<span class="w-sub">' + esc(b.amenities.map(function (a) { return L.amen[a]; }).join(' · ')) + '</span>' : '') + '</div>';
                }
            }
        ],
        rowMenu: function (b) {
            var items = [{ label: L.edit, icon: ICON.pencil, href: dashboardUrl + 'booth-edit?id=' + b.id }];
            if (b.booking) {
                items.push({ label: L.openBooking.replace('%s', b.booking.ref), icon: ICON.doc, href: dashboardUrl + 'booth-booking-edit?id=' + b.booking.id });
            } else if (b.status === 'available') {
                items.push({ label: L.book, icon: ICON.cart, href: dashboardUrl + 'booth-booking-create?booth_id=' + b.id });
                items.push({ label: L.close, icon: ICON.lock, onSelect: function () { bulk('close', [b.id]); } });
            } else if (b.status === 'unavailable') {
                items.push({ label: L.open, icon: ICON.unlock, onSelect: function () { bulk('open', [b.id]); } });
            }
            items.push({ separator: true });
            items.push({ label: L.delete, icon: ICON.trash, danger: true, disabled: !!b.booking, onSelect: function () {
                showDeleteConfirm(L.confirmDeleteOne.replace('%s', b.number)).then(function (r) { if (r.isConfirmed) { bulk('delete', [b.id]); } });
            } });
            return items;
        },
        bulkActions: [
            { key: 'open', label: L.open, icon: ICON.unlock, run: function (ids) { bulk('open', ids); } },
            { key: 'close', label: L.close, icon: ICON.lock, run: function (ids) { bulk('close', ids); } },
            { key: 'delete', label: L.delete, icon: ICON.trash, danger: true, run: function (ids) {
                showDeleteConfirm(L.confirmDelete.replace('%d', ids.length)).then(function (r) { if (r.isConfirmed) { bulk('delete', ids); } });
            } }
        ],
        chips: function (state) {
            var f = state.filters, chips = [];
            if (f.search) { chips.push({ label: L.search, value: f.search, clear: function (l) { l.setFilter('search', ''); } }); }
            if (f.type_id) { chips.push({ label: L.type, value: $('#type-filter option:selected').text(), clear: function (l) { l.setFilter('type_id', ''); } }); }
            return chips;
        }
    });

    /* ------------------------------------------------------- add booths */

    function previewNumbers() {
        var prefix = String($('#add-prefix').val() || '').toUpperCase().replace(/[^A-Z0-9-]/g, '');
        var start = Math.max(0, parseInt($('#add-start').val(), 10) || 0);
        var count = Math.min(200, Math.max(0, parseInt($('#add-count').val(), 10) || 0));
        var pad = $('#add-form [name="pad"]').is(':checked') ? String(start + count - 1).length : 0;
        var num = function (n) { var s = String(n); while (s.length < pad) { s = '0' + s; } return prefix + s; };
        $('#add-preview').text(count ? L.preview.replace('%1$s', num(start)).replace('%2$s', num(start + count - 1)) : '');
    }
    $('#add-form').on('input change', previewNumbers);
    $('#add-booths').on('click', function () {
        $('#add-form .w-field__error').remove();
        var tf = $('#type-filter').val();
        if (tf) { $('#add-type').val(tf); }
        previewNumbers();
        $('#addModal').modal('show');
    });
    $('#add-form').on('submit', function (e) {
        e.preventDefault();
        var btn = $('#add-go').prop('disabled', true);
        $('#add-form .w-field__error').remove();
        $.post(scDashboard.ajaxurl, $(this).serialize() + '&action=sc_booths_generate&nonce=' + encodeURIComponent(scDashboard.nonce))
            .done(function (res) {
                if (!res.success) {
                    var errs = res.data && res.data.errors || {};
                    Object.keys(errs).forEach(function (k) { $('#add-form [name="' + k + '"]').closest('.w-field').append('<p class="w-field__error">' + esc(errs[k]) + '</p>'); });
                    if (!Object.keys(errs).length) { showError(res.data && res.data.message || L.failed); }
                    return;
                }
                $('#addModal').modal('hide');
                (res.data.skipped ? showWarning : showSuccess)(res.data.message);
                list.reload();
            })
            .fail(function () { showError(L.failed); })
            .always(function () { btn.prop('disabled', false); });
    });
    if (/[?&]add=1/.test(location.search)) {
        history.replaceState(null, '', location.pathname + location.search.replace(/[?&]add=1/, '').replace(/^&/, '?'));
        setTimeout(function () { $('#add-booths').trigger('click'); }, 300);
    }
});
</script>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
