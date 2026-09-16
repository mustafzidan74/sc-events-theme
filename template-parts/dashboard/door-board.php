<?php
/**
 * Door board — the entrance on one screen: who is in, how fast people are
 * arriving, what each door is doing. Refreshes itself every few seconds and
 * has a full-screen mode for a TV at the entrance.
 *
 * Data: sc_door_board (inc/admin-dashboard/door-board.php).
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

$events = $wpdb->get_results("SELECT id, title, start_date FROM {$wpdb->prefix}sc_events WHERE status IN ('publish', 'completed') ORDER BY start_date DESC LIMIT 50");
$event_id = isset($_GET['event_id']) ? absint($_GET['event_id']) : 0;
if (!$event_id || !in_array($event_id, array_map('intval', wp_list_pluck($events, 'id')), true)) {
    $event_id = sc_door_board_default_event();
}
$initial = $event_id ? sc_door_board_data($event_id, isset($_GET['day']) ? sanitize_text_field(wp_unslash($_GET['day'])) : '') : null;
if (is_wp_error($initial)) {
    $initial = null;
}

$T = array(
    'registered'  => sc_t('board.registered', 'Registered'),
    'arrived'     => sc_t('board.arrived', 'Arrived'),
    'inside'      => sc_t('board.inside', 'Inside now'),
    'to_come'     => sc_t('board.to_come', 'Still to come'),
    'of'          => sc_t('board.of_registered', '%s%% of registered'),
    'rate'        => sc_t('board.rate', '%s a minute over the last 10 minutes'),
    'rate_none'   => sc_t('board.rate_none', 'Nobody in the last 10 minutes'),
    'left_out'    => sc_t('board.left', 'Left for the day or checked out: %s'),
    'no_tracking' => sc_t('board.no_tracking', 'This event does not record exits'),
    'quiet'       => sc_t('board.quiet', 'No scan for more than %1$s minutes at: %2$s'),
    'updated'     => sc_t('board.updated', 'Updated %s'),
    'stale'       => sc_t('board.stale', 'Not updating — check the connection. Last update %s'),
    'peak'        => sc_t('board.peak', 'Busiest %1$s minutes: %2$s people'),
    'no_scans'    => sc_t('board.no_scans', 'No scans yet on this day.'),
    'offline'     => sc_t('board.offline', 'sent later'),
    'out'         => sc_t('board.out', 'out'),
    'workshop'    => sc_t('board.workshop', 'workshop'),
    'silent'      => sc_t('board.silent', 'quiet %s min'),
    'last15'      => sc_t('board.last15', '%s in 15 min'),
    'hidden'      => sc_t('board.hidden_name', 'Name hidden'),
);

$page_title = sc_t('nav.door_board', 'Door board');
get_template_part('template-parts/dashboard/components/dashboard', 'header');
get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');
?>
<link rel="stylesheet" href="<?php echo esc_url(sc_dashboard_asset('dashboard/css/door-board.css')); ?>">

<div id="main-content">
<div class="container-fluid">

    <div class="w-page-head w-board__head">
        <div>
            <h1><?php echo esc_html($page_title); ?></h1>
            <p class="w-page-head__sub"><?php echo esc_html(sc_t('board.sub', 'The entrance while the doors are open: who is in, how fast people arrive, and which door has gone quiet. Updates every few seconds.')); ?></p>
        </div>
        <div class="w-board__controls">
            <select class="form-control" id="board-event" aria-label="<?php echo esc_attr(sc_t('events.event', 'Event')); ?>">
                <?php foreach ($events as $ev): ?>
                    <option value="<?php echo esc_attr($ev->id); ?>" <?php selected($event_id, (int) $ev->id); ?>><?php echo esc_html($ev->title); ?></option>
                <?php endforeach; ?>
            </select>
            <select class="form-control" id="board-day" aria-label="<?php echo esc_attr(sc_t('board.day', 'Day')); ?>">
                <?php foreach ($initial ? $initial['days'] : array() as $d): ?>
                    <option value="<?php echo esc_attr($d); ?>" <?php selected($initial['day'], $d); ?>><?php echo esc_html(date_i18n('D j M', strtotime($d))); ?></option>
                <?php endforeach; ?>
            </select>
            <label class="w-board__toggle">
                <input type="checkbox" id="board-names">
                <span><?php echo esc_html(sc_t('board.hide_names', 'Hide names')); ?></span>
            </label>
            <button type="button" class="btn btn-outline-secondary" id="board-tv" aria-pressed="false"><?php echo esc_html(sc_t('board.full_screen', 'Full screen')); ?></button>
        </div>
    </div>

    <?php if (!$initial): ?>
        <div class="w-panel"><p class="w-panel__empty"><?php echo esc_html(sc_t('board.no_event', 'There is no event to show yet.')); ?></p></div>
    <?php else: ?>

    <div class="w-board" id="board" aria-live="off">
        <div class="w-board__tvtitle" id="board-title"><?php echo esc_html($initial['event']['title']); ?></div>

        <p class="w-board__alert" id="board-quiet" role="status" hidden></p>

        <div class="w-kpis w-board__kpis">
            <div class="w-kpi w-board__kpi w-board__kpi--lead">
                <span class="w-kpi__label" id="k-main-label"></span>
                <span class="w-kpi__value" id="k-main">—</span>
                <span class="w-kpi__sub" id="k-main-sub"></span>
            </div>
            <div class="w-kpi w-board__kpi">
                <span class="w-kpi__label"><?php echo esc_html($T['arrived']); ?></span>
                <span class="w-kpi__value" id="k-arrived">—</span>
                <span class="w-kpi__sub" id="k-share"></span>
                <span class="w-board__meter" aria-hidden="true"><span id="k-meter"></span></span>
            </div>
            <div class="w-kpi w-board__kpi">
                <span class="w-kpi__label"><?php echo esc_html($T['to_come']); ?></span>
                <span class="w-kpi__value" id="k-tocome">—</span>
                <span class="w-kpi__sub" id="k-registered"></span>
            </div>
            <div class="w-kpi w-board__kpi">
                <span class="w-kpi__label"><?php echo esc_html(sc_t('board.pace', 'Arriving')); ?></span>
                <span class="w-kpi__value" id="k-rate">—</span>
                <span class="w-kpi__sub" id="k-rate-sub"></span>
            </div>
        </div>

        <div class="w-board__grid">
            <section class="w-panel w-board__chart" aria-labelledby="board-chart-title">
                <div class="w-panel__head">
                    <h2 id="board-chart-title"><?php echo esc_html(sprintf(sc_t('board.arrivals_every', 'Arrivals every %s minutes'), SC_DOOR_BOARD_SLOT)); ?></h2>
                    <span class="w-panel__hint" id="board-peak"></span>
                </div>
                <div class="w-bars" id="board-bars" role="img"></div>
                <div class="w-bars__axis w-ltr"><span id="board-axis-from"></span><span id="board-axis-to"></span></div>
            </section>

            <section class="w-panel" aria-labelledby="board-doors-title">
                <div class="w-panel__head">
                    <h2 id="board-doors-title"><?php echo esc_html(sc_t('board.doors', 'Doors')); ?></h2>
                    <span class="w-panel__hint"><?php echo esc_html(sc_t('board.doors_hint', 'One line per scanning account')); ?></span>
                </div>
                <ul class="w-board__doors" id="board-doors"></ul>
            </section>

            <section class="w-panel" aria-labelledby="board-feed-title">
                <div class="w-panel__head">
                    <h2 id="board-feed-title"><?php echo esc_html(sc_t('board.latest', 'Latest scans')); ?></h2>
                </div>
                <ol class="w-board__feed" id="board-feed"></ol>
            </section>

            <section class="w-panel" aria-labelledby="board-ws-title" id="board-ws-panel">
                <div class="w-panel__head">
                    <h2 id="board-ws-title"><?php echo esc_html(sc_t('board.workshops_exhibitors', 'Workshops and exhibitors')); ?></h2>
                </div>
                <ul class="w-board__ws" id="board-ws"></ul>
            </section>
        </div>

        <p class="w-board__updated" id="board-updated"></p>
    </div>
    <?php endif; ?>

</div>
</div>

<?php if ($initial): ?>
<script>
(function () {
    'use strict';
    var T = <?php echo wp_json_encode($T); ?>;
    var state = <?php echo wp_json_encode(array('event' => $initial['event']['id'], 'day' => $initial['day'])); ?>;
    var first = <?php echo wp_json_encode($initial, JSON_HEX_TAG | JSON_HEX_AMP); ?>;
    var EVERY = 5000;
    var lastOk = Date.now();
    var timer = null;
    var inFlight = false;
    var $ = function (id) { return document.getElementById(id); };

    function num(n) { return Number(n || 0).toLocaleString(); }
    function fmt(s, a, b) { return String(s).replace('%1$s', a).replace('%2$s', b).replace('%s', a).replace('%%', '%'); }
    function el(tag, cls, text) { var e = document.createElement(tag); if (cls) { e.className = cls; } if (text !== undefined) { e.textContent = text; } return e; }

    var hideNames = false;
    try { hideNames = localStorage.getItem('scBoardHideNames') === '1'; } catch (e) {}
    $('board-names').checked = hideNames;

    function render(d) {
        $('board-title').textContent = d.event.title;

        if (d.event.tracking) {
            $('k-main-label').textContent = T.inside;
            $('k-main').textContent = num(d.inside);
            $('k-main-sub').textContent = fmt(T.left_out, num(Math.max(0, d.arrived - d.inside)));
        } else {
            $('k-main-label').textContent = T.arrived;
            $('k-main').textContent = num(d.arrived);
            $('k-main-sub').textContent = T.no_tracking;
        }
        $('k-arrived').textContent = num(d.arrived);
        $('k-share').textContent = fmt(T.of, d.share);
        $('k-meter').style.width = Math.min(100, d.share) + '%';
        $('k-tocome').textContent = num(d.to_come);
        $('k-registered').textContent = T.registered + ': ' + num(d.registered);
        var rate = $('k-rate');
        rate.textContent = d.is_today ? d.per_minute.toLocaleString() : '—';
        if (d.is_today) { rate.appendChild(el('small', 'w-board__unit', '/min')); }
        $('k-rate-sub').textContent = !d.is_today ? '' : (d.last10 ? fmt(T.rate, d.per_minute.toLocaleString()) : T.rate_none);

        var quiet = $('board-quiet');
        quiet.hidden = !d.quiet.length;
        quiet.textContent = d.quiet.length ? fmt(T.quiet, <?php echo (int) SC_DOOR_BOARD_QUIET_MINUTES; ?>, d.quiet.join(', ')) : '';

        // Arrivals chart
        var bars = $('board-bars');
        bars.textContent = '';
        var max = Math.max(1, d.peak);
        d.series.forEach(function (b) {
            var col = el('span', 'w-bars__col' + (b.n ? '' : ' is-zero'));
            var bar = el('span', 'w-bars__bar');
            bar.style.height = Math.max(1.5, b.n / max * 100) + '%';
            col.appendChild(bar);
            col.appendChild(el('span', 'w-bars__tip', b.at + ' · ' + num(b.n)));
            bars.appendChild(col);
        });
        bars.setAttribute('aria-label', num(d.arrived) + ' ' + T.arrived);
        $('board-axis-from').textContent = d.series.length ? d.series[0].at : '';
        $('board-axis-to').textContent = d.series.length ? d.series[d.series.length - 1].at : '';
        $('board-peak').textContent = d.peak ? fmt(T.peak, d.slot, num(d.peak)) : '';

        // Doors
        var doors = $('board-doors');
        doors.textContent = '';
        if (!d.doors.length) { doors.appendChild(el('li', 'w-panel__empty', T.no_scans)); }
        d.doors.forEach(function (door) {
            var li = el('li', 'w-board__door' + (door.quiet ? ' is-quiet' : ''));
            var top = el('span', 'w-board__doorname', door.name);
            var meta = el('span', 'w-board__doormeta');
            meta.appendChild(el('span', '', fmt(T.last15, num(door.recent))));
            if (door.silent !== null && door.silent >= 2) { meta.appendChild(el('span', door.quiet ? 'w-board__warn' : '', fmt(T.silent, door.silent))); }
            else { meta.appendChild(el('span', '', door.last)); }
            if (door.offline) { meta.appendChild(el('span', '', num(door.offline) + ' ' + T.offline)); }
            li.appendChild(top);
            li.appendChild(el('span', 'w-board__doorcount', num(door.scans)));
            li.appendChild(meta);
            doors.appendChild(li);
        });

        // Feed
        var feed = $('board-feed');
        feed.textContent = '';
        if (!d.feed.length) { feed.appendChild(el('li', 'w-panel__empty', T.no_scans)); }
        d.feed.forEach(function (f) {
            var li = el('li', 'w-board__item' + (f.out ? ' is-out' : ''));
            li.appendChild(el('span', 'w-board__time w-ltr', f.at));
            var who = el('span', 'w-board__who');
            var name = el('span', 'w-board__name', hideNames ? T.hidden : (f.name || '—'));
            name.setAttribute('dir', 'auto');
            who.appendChild(name);
            var tags = [];
            if (f.out) { tags.push(T.out); }
            if (f.workshop) { tags.push(T.workshop); }
            if (f.offline) { tags.push(T.offline); }
            who.appendChild(el('span', 'w-board__sub', [f.ticket, f.door].filter(Boolean).join(' · ') + (tags.length ? ' · ' + tags.join(' · ') : '')));
            li.appendChild(who);
            feed.appendChild(li);
        });

        // Workshops and exhibitors
        var ws = $('board-ws');
        ws.textContent = '';
        d.workshops.forEach(function (w) { ws.appendChild(row(w.title, w.time, w.arrived, w.registered)); });
        if (d.companies.total) { ws.appendChild(row(<?php echo wp_json_encode(sc_t('board.exhibitors', 'Exhibitors')); ?>, '', d.companies.arrived, d.companies.total)); }
        $('board-ws-panel').hidden = !ws.children.length;

        $('board-updated').textContent = fmt(T.updated, d.updated);
        $('board-updated').classList.remove('is-stale');
    }

    function row(title, time, arrived, total) {
        var li = el('li', 'w-board__wsrow');
        var name = el('span', 'w-board__wsname', title);
        name.setAttribute('dir', 'auto');
        li.appendChild(name);
        li.appendChild(el('span', 'w-board__wscount w-ltr', num(arrived) + ' / ' + num(total)));
        var meter = el('span', 'w-board__meter');
        var fill = el('span');
        fill.style.width = (total ? Math.min(100, arrived / total * 100) : 0) + '%';
        meter.appendChild(fill);
        li.appendChild(meter);
        if (time) { li.appendChild(el('span', 'w-board__wstime w-ltr', time)); }
        return li;
    }

    function load() {
        if (inFlight || document.hidden) { return; }
        inFlight = true;
        var body = new FormData();
        body.append('action', 'sc_door_board');
        body.append('nonce', scDashboard.nonce);
        body.append('event_id', state.event);
        body.append('day', state.day);
        fetch(scDashboard.ajaxurl, { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res || !res.success) { throw new Error('refused'); }
                lastOk = Date.now();
                render(res.data);
            })
            .catch(function () {
                if (Date.now() - lastOk > 20000) {
                    var u = $('board-updated');
                    u.textContent = fmt(T.stale, new Date(lastOk).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }));
                    u.classList.add('is-stale');
                }
            })
            .then(function () { inFlight = false; });
    }

    function schedule() {
        clearInterval(timer);
        timer = setInterval(load, EVERY);
    }

    function fillDays(days, day) {
        var sel = $('board-day');
        sel.textContent = '';
        days.forEach(function (d) {
            var o = el('option', '', new Date(d + 'T12:00:00').toLocaleDateString([], { weekday: 'short', day: 'numeric', month: 'short' }));
            o.value = d;
            o.selected = d === day;
            sel.appendChild(o);
        });
    }

    $('board-event').addEventListener('change', function () {
        state.event = this.value;
        state.day = '';
        var body = new FormData();
        body.append('action', 'sc_door_board');
        body.append('nonce', scDashboard.nonce);
        body.append('event_id', state.event);
        fetch(scDashboard.ajaxurl, { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res || !res.success) { return; }
                state.day = res.data.day;
                fillDays(res.data.days, res.data.day);
                render(res.data);
            });
    });
    $('board-day').addEventListener('change', function () { state.day = this.value; load(); });
    $('board-names').addEventListener('change', function () {
        hideNames = this.checked;
        try { localStorage.setItem('scBoardHideNames', hideNames ? '1' : '0'); } catch (e) {}
        load();
    });

    // Full screen for a TV at the entrance: the board alone, larger.
    $('board-tv').addEventListener('click', function () {
        var on = !document.body.classList.contains('w-board-tv');
        document.body.classList.toggle('w-board-tv', on);
        this.setAttribute('aria-pressed', on ? 'true' : 'false');
        if (on && document.documentElement.requestFullscreen) { document.documentElement.requestFullscreen().catch(function () {}); }
        if (!on && document.fullscreenElement && document.exitFullscreen) { document.exitFullscreen().catch(function () {}); }
    });
    document.addEventListener('fullscreenchange', function () {
        if (!document.fullscreenElement) {
            document.body.classList.remove('w-board-tv');
            $('board-tv').setAttribute('aria-pressed', 'false');
        }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && document.body.classList.contains('w-board-tv')) { $('board-tv').click(); }
    });
    document.addEventListener('visibilitychange', function () { if (!document.hidden) { load(); } });

    render(first);
    schedule();
})();
</script>
<?php endif; ?>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
