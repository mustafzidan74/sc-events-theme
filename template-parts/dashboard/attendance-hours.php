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
            <table class="w-table w-hours__table">
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
    function fmt(s, a, b) { return String(s).replace('%1$s', a).replace('%2$s', b); }

    function render() {
        var head = document.getElementById('hours-head');
        var body = document.getElementById('hours-body');
        head.innerHTML = '';
        body.innerHTML = '';
        var tr = document.createElement('tr');
        ['<?php echo esc_js(sc_t('attendees.name', 'Name')); ?>', '<?php echo esc_js(sc_t('attendees.phone', 'Phone')); ?>'].forEach(function (h) { tr.appendChild(cell('th', h)); });
        data.days.forEach(function (d) { tr.appendChild(cell('th', new Date(d + 'T12:00:00').toLocaleDateString([], { weekday: 'short', day: 'numeric', month: 'short' }), 'n')); });
        tr.appendChild(cell('th', '<?php echo esc_js(sc_t('hours.hours', 'Hours')); ?>', 'n'));
        data.credits.forEach(function (c) { tr.appendChild(cell('th', c, 'n')); });
        head.appendChild(tr);

        var q = $.trim($('#hours-search').val()).toLowerCase();
        var rows = data.rows.filter(function (r) { return !q || (r.name + ' ' + r.phone + ' ' + r.code + ' ' + r.email).toLowerCase().indexOf(q) !== -1; });
        var withHours = data.rows.filter(function (r) { return r.hours > 0; }).length;
        $('#hours-count').text(fmt(T.people, data.rows.length.toLocaleString(), withHours.toLocaleString()));
        if (!rows.length) {
            var empty = document.createElement('tr');
            var td = cell('td', data.rows.length ? T.no_match : T.none);
            td.colSpan = 3 + data.days.length + data.credits.length;
            empty.appendChild(td);
            body.appendChild(empty);
            return;
        }
        var frag = document.createDocumentFragment();
        rows.forEach(function (r) {
            var row = document.createElement('tr');
            var name = cell('td', r.name);
            name.setAttribute('dir', 'auto');
            row.appendChild(name);
            row.appendChild(cell('td', r.phone, 'w-ltr'));
            r.days.forEach(function (n) { row.appendChild(cell('td', String(n), 'n' + (n ? '' : ' zero'))); });
            row.appendChild(cell('td', String(r.hours), 'n' + (r.hours ? '' : ' zero')));
            r.credits.forEach(function (v) { row.appendChild(cell('td', String(v), 'n credit' + (v ? '' : ' zero'))); });
            frag.appendChild(row);
        });
        body.appendChild(frag);
    }

    function load() {
        $.post(scDashboard.ajaxurl, { action: 'sc_hours_list', nonce: scDashboard.nonce, event_id: eventId }).done(function (res) {
            if (res && res.success) { data = res.data; render(); }
        });
    }

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
