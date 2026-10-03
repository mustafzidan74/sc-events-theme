<?php
/**
 * Attendance hours — the hourly rule's settings for an event, and every congress ticket's hours and
 * credits (Wisdom Education, Health Council, …) as they stand, with a CSV export.
 *
 * Logic and endpoints: inc/admin-dashboard/attendance-hours.php.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

global $wpdb, $load_wd_overview;
$load_wd_overview = true;
$events = $wpdb->get_results("SELECT id, title, start_date, end_date FROM {$wpdb->prefix}sc_events WHERE status IN ('publish', 'completed') ORDER BY start_date DESC LIMIT 50");
$event_id = isset($_GET['event_id']) ? absint($_GET['event_id']) : 0;
if (!$event_id || !in_array($event_id, array_map('intval', wp_list_pluck($events, 'id')), true)) {
    $event_id = function_exists('sc_door_board_default_event') ? sc_door_board_default_event() : (int) ($events[0]->id ?? 0);
}
$settings = $event_id ? sc_hours_settings($event_id) : sc_hours_defaults();
$days = $event_id ? sc_hours_event_days($event_id) : array();
$credits = $settings['credits'];
while (count($credits) < 2) {
    $credits[] = array('label' => '', 'max' => '', 'min' => '');
}

$T = array(
    'saved'    => sc_t('hours.saved', 'Saved.'),
    'failed'   => sc_t('general.failed', 'Something went wrong. Try again.'),
    'none'     => sc_t('hours.none', 'No congress tickets for this event yet.'),
    'no_match' => sc_t('hours.no_match', 'Nobody matches this search.'),
    'people'   => sc_t('hours.people', '%1$s people · %2$s with at least one hour'),
    'edit'      => sc_t('general.edit', 'Edit'),
    'edit_title'=> sc_t('hours.edit_title', 'Correct the hours'),
    'edit_help' => sc_t('hours.edit_help', 'Leave a box empty to use the scans. Hours attended recalculates the credits; a credit typed here is used as it is.'),
    'attended'  => sc_t('hours.attended', 'Hours attended'),
    'from_scans'=> sc_t('hours.from_scans', 'From scans: %s'),
    'note'      => sc_t('hours.note', 'Reason (optional)'),
    'save'      => sc_t('general.save', 'Save'),
    'cancel'    => sc_t('general.cancel', 'Cancel'),
    'reset'     => sc_t('hours.reset', 'Use the scans'),
    'edited'    => sc_t('hours.edited', 'Corrected by hand'),
    'edited_by' => sc_t('hours.edited_by', 'Corrected by %1$s, %2$s'),
    'edited_n'  => sc_t('hours.edited_n', '%s corrected by hand'),
);

$page_title = sc_t('nav.attendance_hours', 'Attendance hours');
get_template_part('template-parts/dashboard/components/dashboard', 'header');
get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');
?>
<style>
.w-hours__grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 180px), 1fr)); gap: 12px; }
.w-hours__credit { display: grid; grid-template-columns: minmax(0, 2fr) minmax(0, 1fr) minmax(0, 1fr); gap: 10px; align-items: end; }
.w-hours__credits { display: flex; flex-direction: column; gap: 10px; margin-top: 14px; }
.w-hours__note { margin: 12px 0 0; color: var(--w-text-2); font-size: 13.5px; }
.w-hours__bar { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: space-between; margin-bottom: 12px; }
.w-hours__bar input { max-width: 320px; }
.w-hours__table td.n, .w-hours__table th.n { text-align: end; font-variant-numeric: tabular-nums; white-space: nowrap; }
.w-hours__table td.zero { color: var(--w-text-3); }
.w-hours__table td.credit { font-weight: 700; color: var(--w-primary); }
.w-hours__table td.credit.zero { font-weight: 400; color: var(--w-text-3); }
.w-hours__table td.edited { background: var(--w-gold-soft, #FBF6E8); }
.w-hours__mark { margin-inline-start: 5px; color: var(--w-gold, #C9A24A); font-size: 12px; }
.w-hours__editbtn { padding: 2px 10px; font-size: 13px; }
@media (max-width: 767.98px) {
  .w-dash .w-hours__table tbody tr { grid-template-columns: minmax(0, 1fr) auto; }
  .w-dash .w-hours__table td.w-table__menu { grid-column: 2; grid-row: 1; }
}
.w-hours__form { display: flex; flex-direction: column; gap: 12px; text-align: start; }
.w-hours__form .w-field { display: flex; flex-direction: column; gap: 4px; }
.w-hours__form label { font-weight: 600; font-size: 14px; }
.w-hours__form .w-sub { font-size: 12.5px; }
.w-hours__help { margin: 0; color: var(--w-text-2); font-size: 13.5px; }
.w-hours__scroll { overflow-x: auto; }
.w-hours__table td:first-child { min-width: 160px; }
@media (max-width: 575.98px) { .w-hours__credit { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); } .w-hours__credit .w-field:first-child { grid-column: 1 / -1; } }
</style>

<div id="main-content">
<div class="container-fluid">

    <div class="w-page-head">
        <div>
            <h1><?php echo esc_html($page_title); ?></h1>
            <p class="w-page-head__sub"><?php echo esc_html(sc_t('hours.sub', 'Each scan at the congress entrance counts its whole clock hour, once. The hours become the credit hours printed on the certificate.')); ?></p>
        </div>
        <div class="w-page-head__actions">
            <select class="form-control" id="hours-event" aria-label="<?php echo esc_attr(sc_t('events.event', 'Event')); ?>">
                <?php foreach ($events as $ev): ?>
                    <option value="<?php echo esc_attr($ev->id); ?>" <?php selected($event_id, (int) $ev->id); ?>><?php echo esc_html($ev->title); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <?php if (!$event_id): ?>
        <div class="w-section"><p class="mb-0"><?php echo esc_html(sc_t('board.no_event', 'There is no event to show yet.')); ?></p></div>
    <?php else: ?>

    <form class="w-section" id="hours-form" novalidate>
        <h2 class="w-section__title"><?php echo esc_html(sc_t('hours.rule', 'The rule')); ?></h2>
        <label class="w-check-line mb-3" for="hours-enabled">
            <input type="checkbox" class="w-check" id="hours-enabled" name="enabled" value="1" <?php checked($settings['enabled']); ?>>
            <?php echo esc_html(sc_t('hours.enable', 'Count attendance hours for this event (the scanner switches to one scan per hour)')); ?>
        </label>
        <div class="w-hours__grid">
            <div class="w-field">
                <label for="hours-start"><?php echo esc_html(sc_t('hours.day_start', 'Day starts')); ?></label>
                <select class="form-control" id="hours-start" name="day_start">
                    <?php for ($h = 5; $h <= 14; $h++): ?><option value="<?php echo $h; ?>" <?php selected($settings['day_start'], $h); ?>><?php echo esc_html(sprintf('%02d:00', $h)); ?></option><?php endfor; ?>
                </select>
            </div>
            <div class="w-field">
                <label for="hours-end"><?php echo esc_html(sc_t('hours.day_end', 'Day ends')); ?></label>
                <select class="form-control" id="hours-end" name="day_end">
                    <?php for ($h = 10; $h <= 24; $h++): ?><option value="<?php echo $h; ?>" <?php selected($settings['day_end'], $h); ?>><?php echo esc_html(sprintf('%02d:00', $h)); ?></option><?php endfor; ?>
                </select>
            </div>
            <div class="w-field">
                <span class="w-field__label"><?php echo esc_html(sc_t('hours.total', 'Hours in the congress')); ?></span>
                <strong id="hours-total" class="w-ltr"><?php echo esc_html(count($days) * ($settings['day_end'] - $settings['day_start'])); ?></strong>
                <span class="w-sub"><?php echo esc_html(sprintf(_n('%d day', '%d days', count($days), 'sc_events'), count($days))); ?></span>
            </div>
        </div>
        <div class="w-hours__credits">
            <?php foreach ($credits as $i => $c): ?>
                <div class="w-hours__credit">
                    <div class="w-field">
                        <label for="credit-<?php echo $i; ?>-label"><?php echo esc_html(sprintf(sc_t('hours.credit_name', 'Credit %d'), $i + 1)); ?></label>
                        <input type="text" class="form-control" id="credit-<?php echo $i; ?>-label" name="credits[<?php echo $i; ?>][label]" value="<?php echo esc_attr($c['label']); ?>">
                    </div>
                    <div class="w-field">
                        <label for="credit-<?php echo $i; ?>-max"><?php echo esc_html(sc_t('hours.credit_max', 'Full attendance')); ?></label>
                        <input type="number" class="form-control w-ltr" min="0" step="0.5" id="credit-<?php echo $i; ?>-max" name="credits[<?php echo $i; ?>][max]" value="<?php echo esc_attr($c['max']); ?>">
                    </div>
                    <div class="w-field">
                        <label for="credit-<?php echo $i; ?>-min"><?php echo esc_html(sc_t('hours.credit_min', 'Minimum (one scan)')); ?></label>
                        <input type="number" class="form-control w-ltr" min="0" step="0.5" id="credit-<?php echo $i; ?>-min" name="credits[<?php echo $i; ?>][min]" value="<?php echo esc_attr($c['min']); ?>">
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="w-hours__note"><?php echo esc_html(sc_t('hours.formula', 'Credit = hours counted × full attendance ÷ hours in the congress, rounded to a whole number. Anyone with at least one hour gets the minimum. Workshops do not count.')); ?></p>
        <div class="w-form-actions mt-3"><button type="submit" class="btn btn-primary" id="hours-save"><?php echo esc_html(sc_t('general.save', 'Save')); ?></button></div>
    </form>

    <section class="w-section">
        <div class="w-hours__bar">
            <input type="search" class="form-control" id="hours-search" placeholder="<?php echo esc_attr(sc_t('hours.search', 'Search name, phone or code')); ?>" aria-label="<?php echo esc_attr(sc_t('hours.search', 'Search name, phone or code')); ?>">
            <span class="w-sub" id="hours-count"></span>
            <a class="btn btn-outline-secondary" id="hours-export" href="<?php echo esc_url(add_query_arg(array('action' => 'sc_hours_export', 'event_id' => $event_id, 'nonce' => wp_create_nonce('sc_dashboard_nonce')), admin_url('admin-ajax.php'))); ?>"><?php echo esc_html(sc_t('general.export_csv', 'Export CSV')); ?></a>
        </div>
        <div class="w-table-scroll w-hours__scroll">
            <table class="w-table w-table--nocheck w-hours__table">
                <thead id="hours-head"></thead>
                <tbody id="hours-body"><tr><td><?php echo esc_html(sc_t('general.loading', 'Loading…')); ?></td></tr></tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>

</div>
</div>

<?php if ($event_id): ?>
<script>
jQuery(function ($) {
    'use strict';
    var T = <?php echo wp_json_encode($T); ?>;
    var eventId = <?php echo (int) $event_id; ?>;
    var data = null;

    function cell(tag, text, cls) { var e = document.createElement(tag); e.textContent = text; if (cls) { e.className = cls; } return e; }
    function fmt(s, a, b) { return String(s).replace('%1$s', a).replace('%2$s', b).replace('%s', a); }

    function render() {
        var head = document.getElementById('hours-head');
        var body = document.getElementById('hours-body');
        head.innerHTML = '';
        body.innerHTML = '';
        var tr = document.createElement('tr');
        var phoneLabel = '<?php echo esc_js(sc_t('attendees.phone', 'Phone')); ?>';
        var hoursLabel = '<?php echo esc_js(sc_t('hours.hours', 'Hours')); ?>';
        var dayLabels = data.days.map(function (d) { return new Date(d + 'T12:00:00').toLocaleDateString([], { weekday: 'short', day: 'numeric', month: 'short' }); });
        ['<?php echo esc_js(sc_t('attendees.name', 'Name')); ?>', phoneLabel].forEach(function (h) { tr.appendChild(cell('th', h)); });
        dayLabels.forEach(function (d) { tr.appendChild(cell('th', d, 'n')); });
        tr.appendChild(cell('th', hoursLabel, 'n'));
        data.credits.forEach(function (c) { tr.appendChild(cell('th', c, 'n')); });
        tr.appendChild(cell('th', ''));
        head.appendChild(tr);

        var q = $.trim($('#hours-search').val()).toLowerCase();
        var rows = data.rows.filter(function (r) { return !q || (r.name + ' ' + r.phone + ' ' + r.code + ' ' + r.email).toLowerCase().indexOf(q) !== -1; });
        var withHours = data.rows.filter(function (r) { return r.hours > 0; }).length;
        var edited = data.rows.filter(function (r) { return r.edit; }).length;
        $('#hours-count').text(fmt(T.people, data.rows.length.toLocaleString(), withHours.toLocaleString()) + (edited ? ' · ' + T.edited_n.replace('%s', edited.toLocaleString()) : ''));
        if (!rows.length) {
            var empty = document.createElement('tr');
            var td = cell('td', data.rows.length ? T.no_match : T.none);
            td.colSpan = 4 + data.days.length + data.credits.length;
            empty.appendChild(td);
            body.appendChild(empty);
            return;
        }
        var frag = document.createDocumentFragment();
        rows.forEach(function (r) {
            var row = document.createElement('tr');
            var name = cell('td', r.name, 'w-table__primary');
            name.setAttribute('dir', 'auto');
            row.appendChild(name);
            var ph = cell('td', r.phone, 'w-ltr');
            ph.setAttribute('data-label', phoneLabel);
            row.appendChild(ph);
            r.days.forEach(function (n, i) { var d = cell('td', String(n), 'n' + (n ? '' : ' zero')); d.setAttribute('data-label', dayLabels[i]); row.appendChild(d); });
            var e = r.edit;
            var why = e ? [fmt(T.from_scans, r.counted), e.note, e.by ? fmt(T.edited_by, e.by, e.at) : ''].filter(Boolean).join(' · ') : '';
            var hCell = cell('td', String(r.hours), 'n' + (r.hours ? '' : ' zero') + (e && e.hours !== null ? ' edited' : ''));
            if (e && e.hours !== null) { hCell.title = why; hCell.appendChild(cell('span', '✎', 'w-hours__mark')); }
            hCell.setAttribute('data-label', hoursLabel);
            row.appendChild(hCell);
            r.credits.forEach(function (v, i) {
                var hand = e && e.credits && e.credits[i] !== null && e.credits[i] !== undefined;
                var c = cell('td', String(v), 'n credit' + (v ? '' : ' zero') + (hand ? ' edited' : ''));
                if (hand) { c.title = why; c.appendChild(cell('span', '✎', 'w-hours__mark')); }
                c.setAttribute('data-label', data.credits[i]);
                row.appendChild(c);
            });
            var act = document.createElement('td');
            act.className = 'w-table__menu';
            var btn = cell('button', T.edit, 'btn btn-outline-secondary btn-sm w-hours__editbtn');
            btn.title = why || T.edit;
            btn.type = 'button';
            btn.setAttribute('data-id', r.id);
            btn.setAttribute('aria-label', T.edit + ' — ' + r.name);
            act.appendChild(btn);
            row.appendChild(act);
            frag.appendChild(row);
        });
        body.appendChild(frag);
    }

    function load() {
        $.post(scDashboard.ajaxurl, { action: 'sc_hours_list', nonce: scDashboard.nonce, event_id: eventId }).done(function (res) {
            if (res && res.success) { data = res.data; render(); }
        });
    }

    function field(id, label, value, hint, max) {
        var w = $('<div class="w-field">');
        $('<label>').attr('for', id).text(label).appendTo(w);
        $('<input type="number" class="form-control w-ltr" min="0" step="0.5">').attr({ id: id, max: max, placeholder: hint }).val(value === null || value === undefined ? '' : value).appendTo(w);
        $('<span class="w-sub">').text(hint).appendTo(w);
        return w;
    }

    function editRow(r) {
        var e = r.edit || { hours: null, credits: [], note: '' };
        var box = $('<form class="w-hours__form" novalidate>');
        $('<p class="w-hours__help">').text(T.edit_help).appendTo(box);
        box.append(field('he-hours', T.attended, e.hours, fmt(T.from_scans, r.counted), data.total));
        data.credits.forEach(function (label, i) {
            box.append(field('he-credit-' + i, label, e.credits ? e.credits[i] : null, fmt(T.from_scans, r.auto[i]), data.maxes[i]));
        });
        var note = $('<div class="w-field">');
        $('<label for="he-note">').text(T.note).appendTo(note);
        $('<input type="text" class="form-control" id="he-note" maxlength="200">').val(e.note || '').appendTo(note);
        box.append(note);
        Swal.fire({
            title: T.edit_title + ' · ' + r.name,
            html: box[0],
            showCancelButton: true,
            showDenyButton: !!r.edit,
            confirmButtonText: T.save,
            denyButtonText: T.reset,
            cancelButtonText: T.cancel,
            focusConfirm: false,
            width: 520,
            preConfirm: function () {
                return {
                    hours: $('#he-hours').val(),
                    credits: data.credits.map(function (l, i) { return $('#he-credit-' + i).val(); }),
                    note: $('#he-note').val()
                };
            }
        }).then(function (res) {
            if (!res.isConfirmed && !res.isDenied) { return; }
            var v = res.isDenied ? { hours: '', credits: data.credits.map(function () { return ''; }), note: '' } : res.value;
            var payload = { action: 'sc_hours_edit', nonce: scDashboard.nonce, event_id: eventId, attendee_id: r.id, hours: v.hours, note: v.note };
            v.credits.forEach(function (c, i) { payload['credits[' + i + ']'] = c; });
            $.post(scDashboard.ajaxurl, payload).done(function (out) {
                if (out && out.success) {
                    if (typeof showSuccess === 'function') { showSuccess(out.data.message); }
                    load();
                } else if (typeof showError === 'function') {
                    showError((out && out.data && out.data.message) || T.failed);
                }
            }).fail(function () { if (typeof showError === 'function') { showError(T.failed); } });
        });
    }

    $('#hours-body').on('click', '.w-hours__editbtn', function () {
        var id = parseInt(this.getAttribute('data-id'), 10);
        var r = data && data.rows.filter(function (x) { return x.id === id; })[0];
        if (r) { editRow(r); }
    });

    $('#hours-search').on('input', function () { if (data) { render(); } });
    $('#hours-event').on('change', function () { window.location.href = '?event_id=' + encodeURIComponent($(this).val()); });
    $('#hours-form').on('submit', function (e) {
        e.preventDefault();
        var fd = $(this).serializeArray();
        fd.push({ name: 'action', value: 'sc_hours_save' }, { name: 'nonce', value: scDashboard.nonce }, { name: 'event_id', value: eventId });
        $('#hours-save').prop('disabled', true);
        $.post(scDashboard.ajaxurl, $.param(fd)).done(function (res) {
            if (res && res.success) {
                $('#hours-total').text(res.data.total);
                if (typeof showSuccess === 'function') { showSuccess(T.saved); }
                load();
            } else if (typeof showError === 'function') {
                showError((res && res.data && res.data.message) || T.failed);
            }
        }).fail(function () { if (typeof showError === 'function') { showError(T.failed); } }).always(function () { $('#hours-save').prop('disabled', false); });
    });

    load();
    setInterval(function () { if (!document.hidden) { load(); } }, 60000);
});
</script>
<?php endif; ?>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
