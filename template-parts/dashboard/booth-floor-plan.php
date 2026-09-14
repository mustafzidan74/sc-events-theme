<?php
/**
 * Booth floor plan — booths drawn to scale in the hall; drag (or arrow keys) to place them.
 * Data: sc_floor_plan_data / sc_floor_plan_save (inc/admin-dashboard/booths-dashboard.php).
 * Positions are metres from the hall's top-left corner, snapped to half a metre.
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
$p = $wpdb->prefix;

$events = $wpdb->get_results("SELECT e.id, e.title, (SELECT COUNT(*) FROM {$p}sc_booths b WHERE b.event_id = e.id) booths FROM {$p}sc_events e WHERE e.status IN ('publish', 'completed', 'draft') ORDER BY e.start_date DESC LIMIT 200");
$event_id = isset($_GET['event_id']) ? absint($_GET['event_id']) : 0;
if (!$event_id) {
    foreach ($events as $ev) {
        if ((int) $ev->booths) {
            $event_id = (int) $ev->id;
            break;
        }
    }
    if (!$event_id && $events) {
        $event_id = (int) $events[0]->id;
    }
}
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
            <h1><?php echo esc_html(sc_t('booths.floor_plan', 'Floor plan')); ?></h1>
            <p class="w-page-head__sub"><?php echo esc_html(sc_t('booths.plan_sub', 'Booths drawn to scale. Drag a booth to move it, or select it and use the arrow keys; R turns it. Save when the layout looks right.')); ?></p>
        </div>
        <div class="w-page-head__actions">
            <span class="w-dirty" id="plan-dirty" hidden><?php echo esc_html(sc_t('dashboard_pages.unsaved_changes', 'Unsaved changes')); ?></span>
            <a class="btn btn-secondary" id="booths-link" href="<?php echo esc_url($dashboard_url . 'booths?event_id=' . $event_id); ?>"><?php echo esc_html(sc_t('booths.all_booths', 'Booths')); ?></a>
            <button type="button" class="btn btn-primary" id="plan-save" disabled><?php echo esc_html(sc_t('booths.save_plan', 'Save plan')); ?></button>
        </div>
    </div>

    <div class="w-toolbar w-plan__toolbar">
        <select class="form-control w-badges__event" id="plan-event" aria-label="<?php echo esc_attr(sc_t('events.event', 'Event')); ?>">
            <?php foreach ($events as $ev): ?>
                <option value="<?php echo (int) $ev->id; ?>" <?php selected($event_id, (int) $ev->id); ?>><?php echo esc_html($ev->title); ?></option>
            <?php endforeach; ?>
        </select>
        <select class="form-control" id="plan-floor" aria-label="<?php echo esc_attr(sc_t('booths.floor', 'Floor')); ?>" hidden></select>
        <div class="w-plan__hall">
            <label for="hall-w"><?php echo esc_html(sc_t('booths.hall', 'Hall')); ?></label>
            <input type="number" class="form-control" id="hall-w" min="10" max="500" inputmode="numeric" aria-label="<?php echo esc_attr(sc_t('booths.hall_width', 'Hall width in metres')); ?>">
            <span aria-hidden="true">×</span>
            <input type="number" class="form-control" id="hall-d" min="10" max="500" inputmode="numeric" aria-label="<?php echo esc_attr(sc_t('booths.hall_depth', 'Hall depth in metres')); ?>">
            <span>m</span>
        </div>
        <div class="w-plan__zoom" role="group" aria-label="<?php echo esc_attr(sc_t('booths.zoom', 'Zoom')); ?>">
            <button type="button" class="btn btn-secondary btn-sm" data-zoom="-1" aria-label="<?php echo esc_attr(sc_t('booths.zoom_out', 'Zoom out')); ?>">−</button>
            <button type="button" class="btn btn-secondary btn-sm" data-zoom="0"><?php echo esc_html(sc_t('booths.fit', 'Fit')); ?></button>
            <button type="button" class="btn btn-secondary btn-sm" data-zoom="1" aria-label="<?php echo esc_attr(sc_t('booths.zoom_in', 'Zoom in')); ?>">+</button>
        </div>
    </div>

    <div class="w-plan">
        <div class="w-plan__stage" id="plan-stage">
            <svg id="plan-svg" class="w-plan__svg" role="application" aria-label="<?php echo esc_attr(sc_t('booths.floor_plan', 'Floor plan')); ?>" tabindex="0"></svg>
            <div class="w-state" id="plan-empty" hidden></div>
        </div>
        <aside class="w-plan__side">
            <div class="w-aside-card" id="sel-card" hidden>
                <span class="w-aside-card__title" id="sel-title"></span>
                <p class="mb-1" id="sel-type"></p>
                <p class="w-sub mb-2" id="sel-status"></p>
                <div class="w-aside-actions">
                    <button type="button" class="btn btn-sm btn-secondary" id="sel-rotate"><?php echo esc_html(sc_t('booths.rotate', 'Turn')); ?></button>
                    <button type="button" class="btn btn-sm btn-secondary" id="sel-unplace"><?php echo esc_html(sc_t('booths.take_off', 'Take off plan')); ?></button>
                    <a class="btn btn-sm btn-secondary" id="sel-edit" href="#"><?php echo esc_html(sc_t('dashboard_pages.edit', 'Edit')); ?></a>
                    <a class="btn btn-sm btn-primary" id="sel-book" href="#" hidden><?php echo esc_html(sc_t('booths.book', 'Book for a company')); ?></a>
                    <a class="btn btn-sm btn-secondary" id="sel-booking" href="#" hidden></a>
                </div>
            </div>
            <div class="w-aside-card">
                <span class="w-aside-card__title"><?php echo esc_html(sc_t('booths.not_placed', 'Not on the plan yet')); ?> <span class="w-sub" id="tray-count"></span></span>
                <div class="w-plan__tray" id="plan-tray"></div>
                <button type="button" class="btn btn-sm btn-secondary mt-2" id="auto-place" hidden><?php echo esc_html(sc_t('booths.place_rest', 'Place them in rows')); ?></button>
            </div>
            <div class="w-aside-card">
                <span class="w-aside-card__title"><?php echo esc_html(sc_t('booths.key', 'Key')); ?></span>
                <ul class="w-plan__legend" id="plan-legend"></ul>
            </div>
        </aside>
    </div>

</div>
</div>

<script>
jQuery(function ($) {
    'use strict';

    var dashboardUrl = <?php echo $js($dashboard_url); ?>;
    var eventId = <?php echo (int) $event_id; ?>;
    var L = <?php echo $js(array(
        'status'    => array('available' => sc_t('booths.available', 'Available'), 'reserved' => sc_t('booths.reserved', 'Reserved'), 'booked' => sc_t('booths.booked', 'Booked'), 'occupied' => sc_t('booths.occupied', 'Occupied'), 'unavailable' => sc_t('booths.closed', 'Closed')),
        'boothX'    => sc_t('booths.booth_x', 'Booth %s'),
        'typeSize'  => sc_t('booths.type_size', '%1$s · %2$s × %3$s m'),
        'heldBy'    => sc_t('booths.held_by_short', '%1$s — %2$s'),
        'openBk'    => sc_t('booths.open_booking', 'Open booking %s'),
        'floorN'    => sc_t('booths.floor_n', 'Floor %s'),
        'allPlaced' => sc_t('booths.all_placed', 'Every booth is on the plan.'),
        'noBooths'  => sc_t('booths.no_booths_plan', 'This event has no booths yet. Add them on the booths page, then place them here.'),
        'addBooths' => sc_t('booths.add_booths', 'Add booths'),
        'place'     => sc_t('booths.place', 'Place'),
        'noRoom'    => sc_t('booths.no_room', 'The hall is full. Make it bigger to place the rest.'),
        'leave'     => sc_t('dashboard_pages.unsaved_leave', 'You have unsaved changes.'),
        'saved'     => sc_t('booths.plan_saved', 'Floor plan saved.'),
        'failed'    => sc_t('errors.something_wrong', 'Something went wrong. Please try again.'),
    )); ?>;
    var COLORS = { available: '#1f9d6b', reserved: '#c98a14', booked: '#2f6fd1', occupied: '#6d4bc4', unavailable: '#9aa0a6' };
    var SVGNS = 'http://www.w3.org/2000/svg';
    var svg = document.getElementById('plan-svg');
    var stage = document.getElementById('plan-stage');
    var hall = { width: 60, depth: 40 };
    var booths = [];
    var floor = null;
    var selectedId = null;
    var zoom = 1;
    var dirty = false;

    function el(name, attrs, parent) {
        var n = document.createElementNS(SVGNS, name);
        Object.keys(attrs || {}).forEach(function (k) { n.setAttribute(k, attrs[k]); });
        if (parent) { parent.appendChild(n); }
        return n;
    }
    function dims(b) { return b.rotation ? { w: b.d, d: b.w } : { w: b.w, d: b.d }; }
    function snap(v) { return Math.round(v * 2) / 2; }
    function clamp(b) {
        var s = dims(b);
        b.x = Math.max(0, Math.min(hall.width - s.w, snap(b.x)));
        b.y = Math.max(0, Math.min(hall.depth - s.d, snap(b.y)));
        // 0,0 is stored as 'not placed yet'.
        if (b.x === 0 && b.y === 0) { b.x = 0.5; }
    }
    function setDirty(on) {
        dirty = on;
        $('#plan-dirty').prop('hidden', !on);
        $('#plan-save').prop('disabled', !on);
    }
    window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = L.leave; } });
    var onFloor = function (b) { return floor === null || b.floor === floor; };

    /* ------------------------------------------------------------ render */

    function render() {
        svg.setAttribute('viewBox', '-1 -1 ' + (hall.width + 2) + ' ' + (hall.depth + 2));
        svg.style.width = (zoom * 100) + '%';
        svg.innerHTML = '';
        el('rect', { x: 0, y: 0, width: hall.width, height: hall.depth, class: 'w-plan__hallrect' }, svg);
        var grid = el('g', { class: 'w-plan__grid' }, svg);
        for (var x = 1; x < hall.width; x++) { el('line', { x1: x, y1: 0, x2: x, y2: hall.depth, class: x % 5 ? '' : 'is-major' }, grid); }
        for (var y = 1; y < hall.depth; y++) { el('line', { x1: 0, y1: y, x2: hall.width, y2: y, class: y % 5 ? '' : 'is-major' }, grid); }

        booths.filter(function (b) { return b.placed && onFloor(b); }).forEach(function (b) {
            var s = dims(b);
            var g = el('g', { class: 'w-plan__booth' + (b.id === selectedId ? ' is-selected' : ''), 'data-id': b.id, transform: 'translate(' + b.x + ' ' + b.y + ')', role: 'button', 'aria-label': L.boothX.replace('%s', b.number) + ', ' + (L.status[b.status] || b.status) }, svg);
            el('rect', { width: s.w, height: s.d, fill: COLORS[b.status] || COLORS.available, class: 'w-plan__fill' }, g);
            el('rect', { width: Math.min(0.35, s.w), height: s.d, fill: b.color, class: 'w-plan__typebar' }, g);
            var size = Math.max(0.45, Math.min(1.1, s.w / Math.max(3, b.number.length) * 1.6, s.d * 0.4));
            var t = el('text', { x: s.w / 2, y: s.d / 2 + (b.company ? -size * 0.15 : size * 0.35), 'font-size': size, class: 'w-plan__num' }, g);
            t.textContent = b.number;
            if (b.company && s.d >= 2) {
                var c = el('text', { x: s.w / 2, y: s.d / 2 + size * 0.75, 'font-size': size * 0.5, class: 'w-plan__co' }, g);
                c.textContent = b.company.length > 18 ? b.company.slice(0, 17) + '…' : b.company;
            }
        });

        var tray = booths.filter(function (b) { return !b.placed && onFloor(b); });
        $('#tray-count').text(tray.length ? '(' + tray.length + ')' : '');
        $('#plan-tray').html(tray.length ? tray.map(function (b) {
            return '<button type="button" class="w-boothchip w-plan__trayitem" data-place="' + b.id + '" style="--c:' + WDList.esc(b.color) + '" title="' + WDList.esc(L.place + ' ' + b.number) + '">' + WDList.esc(b.number) + '</button>';
        }).join('') : '<p class="w-sub mb-0">' + WDList.esc(booths.length ? L.allPlaced : '') + '</p>');
        $('#auto-place').prop('hidden', tray.length < 2);
        $('#plan-empty').prop('hidden', booths.length > 0).html('<p>' + WDList.esc(L.noBooths) + '</p><a class="btn btn-primary" href="' + WDList.esc(dashboardUrl + 'booths?event_id=' + eventId + '&add=1') + '">' + WDList.esc(L.addBooths) + '</a>');
        $(svg).prop('hidden', booths.length === 0);
        renderSelection();
    }

    function renderSelection() {
        var b = booths.filter(function (x) { return x.id === selectedId; })[0];
        $('#sel-card').prop('hidden', !b);
        if (!b) { return; }
        $('#sel-title').text(L.boothX.replace('%s', b.number) + (b.name ? ' — ' + b.name : ''));
        $('#sel-type').text(L.typeSize.replace('%1$s', b.type).replace('%2$s', b.w).replace('%3$s', b.d));
        $('#sel-status').text(b.company ? L.heldBy.replace('%1$s', L.status[b.status] || b.status).replace('%2$s', b.company) : (L.status[b.status] || b.status));
        $('#sel-edit').attr('href', dashboardUrl + 'booth-edit?id=' + b.id);
        $('#sel-book').prop('hidden', b.status !== 'available').attr('href', dashboardUrl + 'booth-booking-create?booth_id=' + b.id);
        $('#sel-booking').prop('hidden', !b.booking_id).text(L.openBk.replace('%s', b.ref)).attr('href', dashboardUrl + 'booth-booking-edit?id=' + b.booking_id);
        $('#sel-rotate, #sel-unplace').prop('disabled', !b.placed);
    }

    function legend() {
        $('#plan-legend').html(Object.keys(COLORS).map(function (k) {
            var n = booths.filter(function (b) { return b.status === k && onFloor(b); }).length;
            return '<li><span class="w-swatch" style="background:' + COLORS[k] + '"></span>' + WDList.esc(L.status[k]) + ' <span class="w-sub">' + n + '</span></li>';
        }).join(''));
    }

    /* -------------------------------------------------------------- load */

    function load() {
        $.post(scDashboard.ajaxurl, { action: 'sc_floor_plan_data', nonce: scDashboard.nonce, event_id: eventId }).done(function (res) {
            if (!res.success) { showError(res.data && res.data.message || L.failed); return; }
            hall = res.data.hall;
            booths = res.data.booths;
            $('#hall-w').val(hall.width);
            $('#hall-d').val(hall.depth);
            var floors = booths.map(function (b) { return b.floor; }).filter(function (f, i, a) { return a.indexOf(f) === i; }).sort(function (a, b) { return a - b; });
            floor = floors.length > 1 ? floors[0] : null;
            $('#plan-floor').prop('hidden', floors.length < 2).html(floors.map(function (f) { return '<option value="' + f + '">' + WDList.esc(L.floorN.replace('%s', f)) + '</option>'; }).join(''));
            selectedId = null;
            setDirty(false);
            fit();
            legend();
        }).fail(function () { showError(L.failed); });
    }

    function fit() { zoom = 1; render(); }
    $('[data-zoom]').on('click', function () {
        var d = +this.getAttribute('data-zoom');
        zoom = d === 0 ? 1 : Math.max(1, Math.min(6, zoom * (d > 0 ? 1.5 : 1 / 1.5)));
        render();
    });

    /* -------------------------------------------------------------- drag */

    function toHall(evt) {
        var pt = svg.createSVGPoint();
        pt.x = evt.clientX; pt.y = evt.clientY;
        return pt.matrixTransform(svg.getScreenCTM().inverse());
    }
    var drag = null;
    svg.addEventListener('pointerdown', function (e) {
        var g = e.target.closest('.w-plan__booth');
        if (!g) { selectedId = null; render(); return; }
        var b = booths.filter(function (x) { return x.id === +g.getAttribute('data-id'); })[0];
        var at = toHall(e);
        drag = { b: b, dx: at.x - b.x, dy: at.y - b.y, moved: false, x: b.x, y: b.y };
        selectedId = b.id;
        svg.setPointerCapture(e.pointerId);
        svg.focus({ preventScroll: true });
        render();
        e.preventDefault();
    });
    svg.addEventListener('pointermove', function (e) {
        if (!drag) { return; }
        var at = toHall(e);
        drag.b.x = at.x - drag.dx;
        drag.b.y = at.y - drag.dy;
        clamp(drag.b);
        if (drag.b.x !== drag.x || drag.b.y !== drag.y) {
            drag.moved = true;
            var g = svg.querySelector('[data-id="' + drag.b.id + '"]');
            if (g) { g.setAttribute('transform', 'translate(' + drag.b.x + ' ' + drag.b.y + ')'); }
        }
    });
    function endDrag() {
        if (drag && drag.moved) { setDirty(true); }
        drag = null;
    }
    svg.addEventListener('pointerup', endDrag);
    svg.addEventListener('pointercancel', endDrag);

    svg.addEventListener('keydown', function (e) {
        var b = booths.filter(function (x) { return x.id === selectedId; })[0];
        if (!b || !b.placed) { return; }
        var step = e.shiftKey ? 1 : 0.5;
        var moves = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] };
        if (moves[e.key]) {
            b.x += moves[e.key][0]; b.y += moves[e.key][1];
        } else if (e.key === 'r' || e.key === 'R') {
            b.rotation = b.rotation ? 0 : 90;
        } else if (e.key === 'Escape') {
            selectedId = null; render(); return;
        } else {
            return;
        }
        e.preventDefault();
        clamp(b);
        setDirty(true);
        render();
    });

    /* ------------------------------------------------------ side actions */

    function freeSpot(b, startY) {
        var s = dims(b);
        var placed = booths.filter(function (x) { return x.placed && x !== b && x.floor === b.floor; });
        for (var y = startY || 0; y + s.d <= hall.depth; y += 0.5) {
            for (var x = 0; x + s.w <= hall.width; x += 0.5) {
                var hit = placed.some(function (o) {
                    var os = dims(o);
                    return x < o.x + os.w + 1 && x + s.w + 1 > o.x && y < o.y + os.d + 1 && y + s.d + 1 > o.y;
                });
                if (!hit && (x || y)) { return { x: x, y: y }; }
            }
        }
        return null;
    }
    function place(b) {
        var spot = freeSpot(b);
        if (!spot) { showWarning(L.noRoom); return false; }
        b.x = spot.x; b.y = spot.y; b.placed = true;
        return true;
    }
    $('#plan-tray').on('click', '[data-place]', function () {
        var b = booths.filter(function (x) { return x.id === +this.getAttribute('data-place'); }, this)[0];
        if (b && place(b)) { selectedId = b.id; setDirty(true); render(); }
    });
    $('#auto-place').on('click', function () {
        var changed = false;
        booths.filter(function (b) { return !b.placed && onFloor(b); }).some(function (b) {
            if (!place(b)) { return true; }
            changed = true;
            return false;
        });
        if (changed) { setDirty(true); render(); }
    });
    $('#sel-rotate').on('click', function () {
        var b = booths.filter(function (x) { return x.id === selectedId; })[0];
        if (!b) { return; }
        b.rotation = b.rotation ? 0 : 90;
        clamp(b); setDirty(true); render();
    });
    $('#sel-unplace').on('click', function () {
        var b = booths.filter(function (x) { return x.id === selectedId; })[0];
        if (!b) { return; }
        b.placed = false; b.x = 0; b.y = 0;
        selectedId = null; setDirty(true); render();
    });
    $('#plan-floor').on('change', function () { floor = +this.value; selectedId = null; render(); legend(); });
    $('#hall-w, #hall-d').on('change', function () {
        hall.width = Math.max(10, Math.min(500, parseInt($('#hall-w').val(), 10) || hall.width));
        hall.depth = Math.max(10, Math.min(500, parseInt($('#hall-d').val(), 10) || hall.depth));
        $('#hall-w').val(hall.width); $('#hall-d').val(hall.depth);
        booths.forEach(function (b) { if (b.placed) { clamp(b); } });
        setDirty(true); render();
    });
    $('#plan-event').on('change', function () {
        if (dirty && !window.confirm(L.leave)) { this.value = eventId; return; }
        eventId = +this.value;
        history.replaceState(null, '', location.pathname + '?event_id=' + eventId);
        $('#booths-link').attr('href', dashboardUrl + 'booths?event_id=' + eventId);
        load();
    });

    $('#plan-save').on('click', function () {
        var btn = $(this).prop('disabled', true);
        $.post(scDashboard.ajaxurl, {
            action: 'sc_floor_plan_save', nonce: scDashboard.nonce, event_id: eventId, hall_width: hall.width, hall_depth: hall.depth,
            positions: JSON.stringify(booths.map(function (b) { return { id: b.id, x: b.placed ? b.x : 0, y: b.placed ? b.y : 0, rotation: b.rotation ? 1 : 0 }; }))
        }).done(function (res) {
            if (!res.success) { showError(res.data && res.data.message || L.failed); btn.prop('disabled', false); return; }
            setDirty(false);
            if (window.toastr) { toastr.success(L.saved); }
        }).fail(function () { showError(L.failed); btn.prop('disabled', false); });
    });

    load();
});
</script>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
